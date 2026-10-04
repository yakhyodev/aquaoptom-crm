<?php

namespace App\Services\Operations;

use App\Jobs\ProcessOutboxJob;
use App\Models\OperationResult;
use App\Services\Operations\Exceptions\OperationConflictException;
use App\Services\Operations\Exceptions\OperationException;
use App\Services\Operations\Exceptions\OperationRetryableException;
use App\Services\Operations\Exceptions\OperationValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class TransactionalOperationService
{
    /**
     * Barcha yozuvchi biznes amallarini atomik, idempotent va xavfsiz bajarish.
     *
     * @param  string  $operationId  Barqaror UUID
     * @param  string  $operationType  Operatsiya turi (CREATE_SALE, CREATE_PURCHASE, etc.)
     * @param  array  $payload  Operatsiya parametrlari
     * @param  callable(OperationContext): array  $businessCallback  Haqiqiy biznes operatsiya
     * @param  int|null  $actorId  Foydalanuvchi ID
     * @param  string|null  $deviceId  Qurilma identifikatori
     * @param  string  $source  Manba (web, mobile, telegram)
     * @param  int|null  $expectedVersion  Kutilayotgan versiya
     * @return array Operatsiyaning kanonik natijasi
     *
     * @throws OperationException
     */
    public function execute(
        string $operationId,
        string $operationType,
        array $payload,
        callable $businessCallback,
        ?int $actorId = null,
        ?string $deviceId = null,
        string $source = 'web',
        ?int $expectedVersion = null
    ): array {
        // 1. UUID tekshiruvi
        if (! Str::isUuid($operationId)) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Noto'g'ri operation_id formati: [{$operationId}]. Barqaror UUID bo'lishi shart.",
                errorCode: 'INVALID_OPERATION_ID'
            );
        }

        // 2. Kanonik fingerprint hisoblash
        $fingerprint = PayloadFingerprint::compute($payload);

        // 3. Tranzaksiyadan tashqarida tezkor idempotent tekshiruv (Read-cache / fast path)
        $existing = OperationResult::where('operation_id', $operationId)->first();
        if ($existing) {
            if ($this->matches($existing, $fingerprint, $operationType, $actorId)) {
                // Aynan shu ID va shu ma'lumot — eski natijani darhol qaytaramiz (Idempotent 200 OK)
                return $existing->result_payload ?? [];
            }

            // Shu ID, lekin boshqa ma'lumot — KONFLIKT!
            throw new OperationConflictException(
                operationId: $operationId,
                details: [
                    'original_type' => $existing->operation_type,
                    'original_at' => $existing->created_at?->toIso8601String(),
                ]
            );
        }

        // 4. Ma'lumotlar bazasi tranzaksiyasi ichida atomik bajarish
        try {
            return DB::transaction(function () use (
                $operationId,
                $operationType,
                $fingerprint,
                $businessCallback,
                $actorId,
                $deviceId,
                $source,
                $expectedVersion
            ) {
                // PostgreSQL advisory lock orqali 20 ta parallel so'rovni xavfsiz navbatga tizish
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$operationId]);
                }

                // Lockni olgach, takroran tekshiramiz (boshqa parallel oqim commit qilgan bo'lishi mumkin)
                $lockedExisting = OperationResult::where('operation_id', $operationId)->first();
                if ($lockedExisting) {
                    if ($this->matches($lockedExisting, $fingerprint, $operationType, $actorId)) {
                        return $lockedExisting->result_payload ?? [];
                    }

                    throw new OperationConflictException(
                        operationId: $operationId,
                        details: [
                            'original_type' => $lockedExisting->operation_type,
                            'original_at' => $lockedExisting->created_at?->toIso8601String(),
                        ]
                    );
                }

                $context = new OperationContext(
                    operationId: $operationId,
                    actorId: $actorId,
                    deviceId: $deviceId,
                    source: $source,
                    expectedVersion: $expectedVersion
                );

                // Asosiy biznes logikani ishga tushirish
                $result = $businessCallback($context);

                if (! is_array($result)) {
                    $result = ['result' => $result];
                }

                // Qo'shimcha standart maydonlar
                $result['operation_id'] = $operationId;
                $result['status'] = 'PROCESSED';

                // Outbox hodisalari va AuditLog yozuvlarini shu tranzaksiyada bazaga flush qilamiz
                $context->flushOutboxAndAudit();

                // Operatsiya natijasini shu tranzaksiyada saqlaymiz
                OperationResult::create([
                    'operation_id' => $operationId,
                    'operation_type' => $operationType,
                    'payload_fingerprint' => $fingerprint,
                    'actor_id' => $actorId,
                    'device_id' => $deviceId,
                    'source' => $source,
                    'status' => 'PROCESSED',
                    'expected_version' => $expectedVersion,
                    'result_payload' => $result,
                    'processed_at' => now(),
                    'created_at' => now(),
                ]);

                // Commitdan so'ng Outbox workerini navbatga chaqiramiz
                DB::afterCommit(function () {
                    // Outbox hodisalarini foniy process qilish uchun job dispatch qilinadi
                    dispatch(new ProcessOutboxJob);
                });

                return $result;
            });
        } catch (OperationException $e) {
            // Allaqachon klassifikatsiyalangan domen xatosi
            throw $e;
        } catch (UniqueConstraintViolationException $e) {
            // Agar locksiz DB unikal cheklovi ishga tushgan bo'lsa (concurrency race)
            $racing = OperationResult::where('operation_id', $operationId)->first();
            if ($racing && $this->matches($racing, $fingerprint, $operationType, $actorId)) {
                return $racing->result_payload ?? [];
            }

            throw new OperationConflictException(operationId: $operationId);
        } catch (QueryException $e) {
            // DB deadloack yoki ulanish uzilishi -> retryable
            if (str_contains($e->getMessage(), 'deadlock') || str_contains($e->getMessage(), 'could not serialize')) {
                throw new OperationRetryableException(
                    operationId: $operationId,
                    message: 'Tranzaksiyada parallel ziddiyat (deadlock). Qayta urinib ko\'ring.'
                );
            }

            // Raw SQL xatoliklarini yashiramiz, sir chiqib ketmasligi uchun
            Log::error('Operation database constraint failed.', ['operation_id' => $operationId, 'sql_state' => $e->getCode()]);
            throw new OperationValidationException(
                operationId: $operationId,
                message: 'Ma\'lumotlar bazasi cheklovi buzildi. Kiritilgan qiymatlarni tekshiring.',
                errorCode: 'DB_CONSTRAINT_ERROR'
            );
        } catch (InvalidArgumentException $e) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: $e->getMessage()
            );
        } catch (Throwable $e) {
            Log::error('Unexpected operation failure.', ['operation_id' => $operationId, 'exception_type' => $e::class]);
            // Barcha boshqa kutilmagan raw exceptionlar
            throw new OperationException(
                operationId: $operationId,
                errorCategory: 'needs_review',
                errorCode: 'INTERNAL_OPERATION_ERROR',
                message: 'Operatsiyani bajarishda kutilmagan xatolik yuz berdi.',
                statusCode: 500
            );
        }
    }

    private function matches(OperationResult $result, string $fingerprint, string $operationType, ?int $actorId): bool
    {
        return $result->payload_fingerprint === $fingerprint
            && $result->operation_type === $operationType
            && ($result->actor_id === null ? null : (int) $result->actor_id) === $actorId;
    }

    public function replay(string $operationId, string $operationType, array $payload, ?int $actorId): ?array
    {
        $existing = OperationResult::where('operation_id', $operationId)->first();
        if (! $existing) {
            return null;
        }
        if (! $this->matches($existing, PayloadFingerprint::compute($payload), $operationType, $actorId)) {
            throw new OperationConflictException(operationId: $operationId);
        }

        return $existing->result_payload ?? [];
    }
}
