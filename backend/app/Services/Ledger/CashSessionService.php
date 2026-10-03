<?php

namespace App\Services\Ledger;

use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationPermissionException;
use App\Services\Operations\Exceptions\OperationValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashSessionService
{
    public function __construct(
        protected CashAccountService $cashAccountService
    ) {}

    /**
     * Yangi kassa smenasini ochish.
     * Qoida: Bitta kassa hisobi uchun bir vaqtda faqat bitta OPEN smena bo'lishi mumkin.
     */
    public function openSession(
        int $cashAccountId,
        int $userId,
        ?int $openingBalance = null,
        ?string $operationId = null,
        ?string $notes = null
    ): CashSession {
        $opId = $operationId ?? (string) Str::uuid();

        return DB::transaction(function () use ($cashAccountId, $userId, $openingBalance, $opId, $notes) {
            $account = CashAccount::where('id', $cashAccountId)->lockForUpdate()->firstOrFail();

            $existingOpen = CashSession::where('cash_account_id', $cashAccountId)
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->first();

            if ($existingOpen) {
                throw new OperationValidationException(
                    $opId,
                    "Ushbu kassa uchun ochiq smena allaqachon mavjud (#{$existingOpen->session_number})!",
                    ['existing_session_id' => $existingOpen->id, 'session_number' => $existingOpen->session_number],
                    'ANOTHER_SESSION_ALREADY_OPEN'
                );
            }

            $openingAmount = $openingBalance ?? (int) $account->balance;
            $sessionNumber = DocumentNumberGenerator::nextSessionNumber();

            $session = CashSession::create([
                'session_number' => $sessionNumber,
                'cash_account_id' => $cashAccountId,
                'opened_by' => $userId,
                'status' => 'OPEN',
                'opened_at' => Carbon::now(),
                'opening_balance' => $openingAmount,
                'notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'CASH_SESSION_OPENED',
                'auditable_type' => CashSession::class,
                'auditable_id' => $session->id,
                'old_values' => null,
                'new_values' => [
                    'session_number' => $sessionNumber,
                    'cash_account_id' => $cashAccountId,
                    'opening_balance' => $openingAmount,
                ],
                'created_at' => Carbon::now(),
            ]);

            OutboxEvent::create([
                'event_id' => (string) Str::uuid(),
                'operation_id' => $opId,
                'event_name' => 'CashSessionOpened',
                'aggregate_type' => 'CashSession',
                'aggregate_id' => $session->id,
                'payload' => [
                    'session_id' => $session->id,
                    'session_number' => $sessionNumber,
                    'cash_account_id' => $cashAccountId,
                    'opening_balance' => $openingAmount,
                ],
                'status' => 'PENDING',
            ]);

            return $session;
        });
    }

    /**
     * Smena bo'yicha tizimdagi kutilayotgan naqd qoldiqni hisoblash:
     * boshlang'ich naqd + jami kirimlar - jami chiqimlar.
     */
    public function calculateExpectedBalance(CashSession $session): int
    {
        $inflows = (int) CashMovement::where('cash_session_id', $session->id)
            ->where('direction', 'IN')
            ->sum('amount');

        $outflows = (int) CashMovement::where('cash_session_id', $session->id)
            ->where('direction', 'OUT')
            ->sum('amount');

        return (int) $session->opening_balance + $inflows - $outflows;
    }

    /**
     * Smenani yopish.
     * Invariant: Farq bo'lsa sabab majburiy; farq yashirin balance overwrite bilan tuzatilmaydi.
     * Agar offline sinxronlash kutilayotgan qurilmalar bo'lsa, PROVISIONAL sifatida yopiladi.
     */
    public function closeSession(
        int $sessionId,
        int $userId,
        int $actualClosingBalance,
        ?string $differenceReason = null,
        bool $isProvisional = false,
        ?string $operationId = null,
        ?string $notes = null
    ): CashSession {
        $opId = $operationId ?? (string) Str::uuid();

        return DB::transaction(function () use (
            $sessionId,
            $userId,
            $actualClosingBalance,
            $differenceReason,
            $isProvisional,
            $opId,
            $notes
        ) {
            $session = CashSession::where('id', $sessionId)->lockForUpdate()->firstOrFail();

            if ($session->status !== 'OPEN') {
                throw new OperationValidationException(
                    $opId,
                    "Faqat ochiq smenani yopish mumkin! Hozirgi holati: {$session->status}",
                    ['status' => $session->status],
                    'SESSION_NOT_OPEN'
                );
            }

            $expected = $this->calculateExpectedBalance($session);
            $difference = $actualClosingBalance - $expected;

            if ($difference !== 0 && empty(trim($differenceReason ?? ''))) {
                throw new OperationValidationException(
                    $opId,
                    "Kassa sanalganda kutilgan summadan farq aniqlandi ({$difference} so'm). Farq sababi ko'rsatilishi shart!",
                    [
                        'expected_closing_balance' => $expected,
                        'actual_closing_balance' => $actualClosingBalance,
                        'difference' => $difference,
                    ],
                    'DIFFERENCE_REASON_REQUIRED'
                );
            }

            $session->status = $isProvisional ? 'PROVISIONAL' : 'CLOSED';
            $session->has_pending_offline_sync = $isProvisional;
            $session->closed_at = Carbon::now();
            $session->closed_by = $userId;
            $session->expected_closing_balance = $expected;
            $session->actual_closing_balance = $actualClosingBalance;
            $session->difference = $difference;
            $session->difference_reason = $differenceReason;
            $session->difference_status = ($difference !== 0) ? 'PENDING_APPROVAL' : 'NONE';

            if ($notes) {
                $session->notes = $session->notes ? ($session->notes."\n".$notes) : $notes;
            }

            $session->save();

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'CASH_SESSION_CLOSED',
                'auditable_type' => CashSession::class,
                'auditable_id' => $session->id,
                'old_values' => ['status' => 'OPEN'],
                'new_values' => [
                    'status' => $session->status,
                    'expected_closing_balance' => $expected,
                    'actual_closing_balance' => $actualClosingBalance,
                    'difference' => $difference,
                    'difference_reason' => $differenceReason,
                ],
                'created_at' => Carbon::now(),
            ]);

            OutboxEvent::create([
                'event_id' => (string) Str::uuid(),
                'operation_id' => $opId,
                'event_name' => 'CashSessionClosed',
                'aggregate_type' => 'CashSession',
                'aggregate_id' => $session->id,
                'payload' => [
                    'session_id' => $session->id,
                    'status' => $session->status,
                    'expected' => $expected,
                    'actual' => $actualClosingBalance,
                    'difference' => $difference,
                ],
                'status' => 'PENDING',
            ]);

            return $session;
        });
    }

    /**
     * Kassa farqini rasman tasdiqlash va ruxsatli farq hujjati bilan pul daftariga yozish.
     * Yashirin balance overwrite yo'q: qonuniy DIFFERENCE_SURPLUS yoki DIFFERENCE_SHORTAGE yoziladi.
     */
    public function approveDifference(
        int $sessionId,
        int $approverId,
        bool $adjustCashLedger = true,
        ?string $operationId = null,
        ?string $notes = null
    ): CashSession {
        $opId = $operationId ?? (string) Str::uuid();

        return DB::transaction(function () use ($sessionId, $approverId, $adjustCashLedger, $opId, $notes) {
            $user = User::findOrFail($approverId);
            $hasPermission = $user->hasRole(['OWNER', 'ADMIN']) ||
                $user->hasPermission('approve_cash_discrepancy') ||
                $user->hasPermission('manage_settings');

            if (! $hasPermission) {
                throw new OperationPermissionException(
                    $opId,
                    'Kassa farqini tasdiqlash uchun vakolat yetarli emas!',
                    ['user_id' => $approverId],
                    'PERMISSION_DENIED'
                );
            }

            $session = CashSession::where('id', $sessionId)->lockForUpdate()->firstOrFail();

            if ($session->difference_status !== 'PENDING_APPROVAL') {
                throw new OperationValidationException(
                    $opId,
                    'Ushbu smenada tasdiqlash kutilayotgan farq mavjud emas!',
                    ['difference_status' => $session->difference_status],
                    'NO_PENDING_DIFFERENCE'
                );
            }

            if ($adjustCashLedger && $session->difference !== 0) {
                if ($session->difference > 0) {
                    // Ortiqcha naqd pul (Surplus)
                    $this->cashAccountService->recordInflow(
                        cashAccountId: $session->cash_account_id,
                        amount: $session->difference,
                        type: 'DIFFERENCE_SURPLUS',
                        operationId: $opId,
                        referenceType: 'CashSession',
                        referenceId: $session->id,
                        description: 'Tasdiqlangan kassa ortiqchaligi (Smena #'.$session->session_number.'): '.($session->difference_reason ?: 'Smena farqi'),
                        userId: $approverId,
                        cashSessionId: $session->id
                    );
                } else {
                    // Kamomad (Shortage)
                    $this->cashAccountService->recordOutflow(
                        cashAccountId: $session->cash_account_id,
                        amount: abs($session->difference),
                        type: 'DIFFERENCE_SHORTAGE',
                        operationId: $opId,
                        referenceType: 'CashSession',
                        referenceId: $session->id,
                        description: 'Tasdiqlangan kassa kamomadi (Smena #'.$session->session_number.'): '.($session->difference_reason ?: 'Smena farqi'),
                        userId: $approverId,
                        allowNegative: true,
                        cashSessionId: $session->id
                    );
                }
            }

            $session->difference_status = 'APPROVED';
            $session->difference_approved_by = $approverId;
            $session->difference_approved_at = Carbon::now();

            if ($session->status === 'PROVISIONAL') {
                $session->status = 'CLOSED';
                $session->has_pending_offline_sync = false;
            }

            if ($notes) {
                $session->notes = $session->notes ? ($session->notes."\n".$notes) : $notes;
            }

            $session->save();

            AuditLog::create([
                'user_id' => $approverId,
                'action' => 'CASH_DIFFERENCE_APPROVED',
                'auditable_type' => CashSession::class,
                'auditable_id' => $session->id,
                'old_values' => ['difference_status' => 'PENDING_APPROVAL'],
                'new_values' => [
                    'difference_status' => 'APPROVED',
                    'adjusted_ledger' => $adjustCashLedger,
                    'approved_by' => $approverId,
                ],
                'created_at' => Carbon::now(),
            ]);

            return $session;
        });
    }

    /**
     * Kassa farqini rad etish (balans tuzatishsiz qoldirish).
     */
    public function rejectDifference(
        int $sessionId,
        int $approverId,
        ?string $operationId = null,
        ?string $notes = null
    ): CashSession {
        $opId = $operationId ?? (string) Str::uuid();

        return DB::transaction(function () use ($sessionId, $approverId, $opId, $notes) {
            $user = User::findOrFail($approverId);
            $hasPermission = $user->hasRole(['OWNER', 'ADMIN']) ||
                $user->hasPermission('approve_cash_discrepancy') ||
                $user->hasPermission('manage_settings');

            if (! $hasPermission) {
                throw new OperationPermissionException(
                    $opId,
                    "Kassa farqini ko'rib chiqish uchun vakolat yetarli emas!",
                    ['user_id' => $approverId],
                    'PERMISSION_DENIED'
                );
            }

            $session = CashSession::where('id', $sessionId)->lockForUpdate()->firstOrFail();

            if ($session->difference_status !== 'PENDING_APPROVAL') {
                throw new OperationValidationException(
                    $opId,
                    "Ushbu smenada ko'rib chiqish kutilayotgan farq mavjud emas!",
                    ['difference_status' => $session->difference_status],
                    'NO_PENDING_DIFFERENCE'
                );
            }

            $session->difference_status = 'REJECTED';
            $session->difference_approved_by = $approverId;
            $session->difference_approved_at = Carbon::now();

            if ($notes) {
                $session->notes = $session->notes ? ($session->notes."\n".$notes) : $notes;
            }

            $session->save();

            return $session;
        });
    }
}
