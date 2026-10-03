<?php

namespace App\Services\Ledger;

use App\Models\AuditLog;
use App\Models\CreditAllocation;
use App\Models\CreditAllocationMovement;
use App\Models\Customer;
use App\Models\Device;
use App\Models\User;
use App\Services\Ledger\Exceptions\InsufficientCreditAllocationException;
use App\Services\Operations\Exceptions\OperationValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditAllocationService
{
    /**
     * Qurilmaga mijoz bo'yicha yoki yangi offline mijozlar uchun kredit limiti ajratish.
     *
     * @param  int  $amount  Summa (butun so'm > 0)
     * @param  bool  $isNewCustomerBudget  Yangi offline mijozlar uchun umumiy byudjetmi
     */
    public function grantCreditAllocation(
        Device $device,
        ?Customer $customer,
        int $amount,
        bool $isNewCustomerBudget = false,
        ?string $operationId = null,
        ?int $userId = null,
        ?string $notes = null
    ): CreditAllocation {
        $operationId = $operationId ?: (string) Str::uuid();

        if ($amount <= 0) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Ajratiladigan kredit limiti 0 dan katta butun so'm bo'lishi shart!",
                errorCode: 'INVALID_CREDIT_AMOUNT'
            );
        }

        if (! $device->isActive()) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Qurilma (#{$device->device_code}) faol emas! Unga kredit limiti ajratib bo'lmaydi.",
                errorCode: 'DEVICE_NOT_ACTIVE'
            );
        }

        if (! $isNewCustomerBudget && ! $customer) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: 'Mijoz tanlanmagan va bu yangi mijozlar byudjeti emas!',
                errorCode: 'CUSTOMER_REQUIRED'
            );
        }

        return DB::transaction(function () use (
            $device,
            $customer,
            $amount,
            $isNewCustomerBudget,
            $operationId,
            $userId,
            $notes
        ) {
            $existingMovement = CreditAllocationMovement::where('operation_id', $operationId)->first();
            if ($existingMovement) {
                return $existingMovement->allocation;
            }

            // Agar aniq mijoz uchun bo'lsa va unda qat'iy limit belgilangan bo'lsa
            if (! $isNewCustomerBudget && $customer) {
                $customerLocked = Customer::where('id', $customer->id)->lockForUpdate()->first();
                $debtLimit = (int) $customerLocked->debt_limit;

                if ($debtLimit > 0) {
                    $currentDebt = (int) $customerLocked->current_debt;

                    // Boshqa qurilmalarga ajratilgan faol kredit rezervlari
                    $sumReserved = (int) CreditAllocation::where('customer_id', $customer->id)
                        ->where('status', 'ACTIVE')
                        ->sum(DB::raw('allocated_amount - consumed_amount - returned_amount'));

                    $freeCreditLimit = max(0, $debtLimit - $currentDebt - $sumReserved);

                    if ($amount > $freeCreditLimit) {
                        throw new InsufficientCreditAllocationException(
                            message: "Mijoz '{$customer->name}' uchun yetarli bo'sh kredit limiti mavjud emas! Jami limit: {$debtLimit} so'm, joriy qarz: {$currentDebt} so'm, qurilmalarga band qilingan rezerv: {$sumReserved} so'm, bo'sh limit: {$freeCreditLimit} so'm. Ajratish so'ralgan: {$amount} so'm.",
                            operationId: $operationId,
                            details: [
                                'customer_id' => $customer->id,
                                'debt_limit' => $debtLimit,
                                'current_debt' => $currentDebt,
                                'sum_reserved' => $sumReserved,
                                'free_credit_limit' => $freeCreditLimit,
                                'requested_amount' => $amount,
                            ]
                        );
                    }
                }
            }

            // Allocation yozuvini olish yoki yaratish
            $query = CreditAllocation::where('device_id', $device->id)->where('status', 'ACTIVE');
            if ($isNewCustomerBudget) {
                $query->where('is_new_customer_budget', true);
            } else {
                $query->where('customer_id', $customer->id)->where('is_new_customer_budget', false);
            }

            $allocation = $query->lockForUpdate()->first();

            if (! $allocation) {
                $allocation = CreditAllocation::create([
                    'device_id' => $device->id,
                    'customer_id' => $isNewCustomerBudget ? null : $customer->id,
                    'is_new_customer_budget' => $isNewCustomerBudget,
                    'allocated_amount' => $amount,
                    'consumed_amount' => 0,
                    'returned_amount' => 0,
                    'epoch' => $device->current_lease_epoch,
                    'status' => 'ACTIVE',
                    'notes' => $notes,
                ]);
            } else {
                $allocation->increment('allocated_amount', $amount);
            }

            // Agar yangi mijozlar byudjeti bo'lsa, device modelidagi maydonni ham yangilash
            if ($isNewCustomerBudget) {
                $device->update([
                    'allow_new_offline_customer_debt' => true,
                    'new_customer_debt_budget' => (int) $allocation->allocated_amount,
                ]);
            }

            CreditAllocationMovement::create([
                'credit_allocation_id' => $allocation->id,
                'device_id' => $device->id,
                'customer_id' => $isNewCustomerBudget ? null : $customer?->id,
                'operation_id' => $operationId,
                'movement_type' => 'GRANT',
                'amount' => $amount,
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'CREDIT_ALLOCATION_GRANTED',
                'auditable_type' => CreditAllocation::class,
                'auditable_id' => $allocation->id,
                'new_values' => [
                    'device_id' => $device->id,
                    'customer_id' => $customer?->id,
                    'is_new_customer_budget' => $isNewCustomerBudget,
                    'amount' => $amount,
                    'operation_id' => $operationId,
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);

            return $allocation->fresh();
        });
    }

    /**
     * Qurilma kredit limiti ajratmasidan sarflash (Consume Credit Allocation).
     */
    public function consumeCreditAllocation(
        Device $device,
        ?Customer $customer,
        int $amount,
        string $operationId,
        bool $isNewCustomerBudget = false,
        ?int $userId = null,
        ?string $notes = null
    ): CreditAllocation {
        if ($amount <= 0) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Sarflanadigan kredit miqdori 0 dan katta butun so'm bo'lishi shart!",
                errorCode: 'INVALID_CREDIT_AMOUNT'
            );
        }

        return DB::transaction(function () use (
            $device,
            $customer,
            $amount,
            $operationId,
            $isNewCustomerBudget,
            $userId,
            $notes
        ) {
            $existingMovement = CreditAllocationMovement::where('operation_id', $operationId)->first();
            if ($existingMovement) {
                return $existingMovement->allocation;
            }

            $query = CreditAllocation::where('device_id', $device->id)->where('status', 'ACTIVE');
            if ($isNewCustomerBudget) {
                $query->where('is_new_customer_budget', true);
            } else {
                $query->where('customer_id', $customer->id)->where('is_new_customer_budget', false);
            }

            $allocation = $query->lockForUpdate()->first();
            $available = $allocation ? $allocation->available_amount : 0;

            if (! $allocation || $available < $amount) {
                $targetName = $isNewCustomerBudget ? 'Yangi offline mijozlar byudjeti' : ($customer->name ?? 'Mijoz');
                throw new InsufficientCreditAllocationException(
                    message: "Qurilmada (#{$device->device_code}) '{$targetName}' uchun yetarli kredit ajratmasi mavjud emas! Mavjud rezerv: {$available} so'm, so'ralgan: {$amount} so'm.",
                    operationId: $operationId,
                    details: [
                        'device_id' => $device->id,
                        'customer_id' => $customer?->id,
                        'available_credit' => $available,
                        'requested_amount' => $amount,
                    ]
                );
            }

            $allocation->increment('consumed_amount', $amount);

            if ($isNewCustomerBudget) {
                $device->increment('new_customer_debt_consumed', $amount);
            }

            CreditAllocationMovement::create([
                'credit_allocation_id' => $allocation->id,
                'device_id' => $device->id,
                'customer_id' => $isNewCustomerBudget ? null : $customer?->id,
                'operation_id' => $operationId,
                'movement_type' => 'CONSUME',
                'amount' => $amount,
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            return $allocation->fresh();
        });
    }

    /**
     * Qurilma kredit limiti ajratmasini qaytarish (Return Credit Allocation).
     */
    public function returnCreditAllocation(
        Device $device,
        ?Customer $customer,
        int $amount,
        bool $isNewCustomerBudget = false,
        ?string $operationId = null,
        ?int $userId = null,
        ?string $notes = null
    ): CreditAllocation {
        $operationId = $operationId ?: (string) Str::uuid();

        if ($amount <= 0) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Qaytariladigan kredit miqdori 0 dan katta butun so'm bo'lishi shart!",
                errorCode: 'INVALID_CREDIT_AMOUNT'
            );
        }

        return DB::transaction(function () use (
            $device,
            $customer,
            $amount,
            $isNewCustomerBudget,
            $operationId,
            $userId,
            $notes
        ) {
            $existingMovement = CreditAllocationMovement::where('operation_id', $operationId)->first();
            if ($existingMovement) {
                return $existingMovement->allocation;
            }

            $query = CreditAllocation::where('device_id', $device->id)->where('status', 'ACTIVE');
            if ($isNewCustomerBudget) {
                $query->where('is_new_customer_budget', true);
            } else {
                $query->where('customer_id', $customer->id)->where('is_new_customer_budget', false);
            }

            $allocation = $query->lockForUpdate()->first();
            $available = $allocation ? $allocation->available_amount : 0;

            if (! $allocation || $available < $amount) {
                throw new InsufficientCreditAllocationException(
                    message: "Qurilmada qaytarish uchun yetarli ishlatilmagan kredit rezervi mavjud emas! Qolgan rezerv: {$available} so'm, so'ralgan: {$amount} so'm.",
                    operationId: $operationId
                );
            }

            $allocation->increment('returned_amount', $amount);

            CreditAllocationMovement::create([
                'credit_allocation_id' => $allocation->id,
                'device_id' => $device->id,
                'customer_id' => $isNewCustomerBudget ? null : $customer?->id,
                'operation_id' => $operationId,
                'movement_type' => 'RETURN',
                'amount' => $amount,
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'CREDIT_ALLOCATION_RETURNED',
                'auditable_type' => CreditAllocation::class,
                'auditable_id' => $allocation->id,
                'new_values' => [
                    'device_id' => $device->id,
                    'customer_id' => $customer?->id,
                    'returned_amount' => $amount,
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);

            return $allocation->fresh();
        });
    }

    /**
     * Yo'qolgan qurilmaning kredit rezervlarini qo'lda muvofiqlashtirish.
     */
    public function manualReconcileLostDeviceCredit(Device $device, User $actor, string $reason): void
    {
        DB::transaction(function () use ($device, $actor, $reason) {
            $allocations = CreditAllocation::where('device_id', $device->id)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                $remaining = $allocation->available_amount;

                if ($remaining > 0) {
                    $opId = (string) Str::uuid();

                    $allocation->increment('returned_amount', $remaining);
                    $allocation->update(['status' => 'RECONCILED']);

                    CreditAllocationMovement::create([
                        'credit_allocation_id' => $allocation->id,
                        'device_id' => $device->id,
                        'customer_id' => $allocation->customer_id,
                        'operation_id' => $opId,
                        'movement_type' => 'RECONCILE',
                        'amount' => $remaining,
                        'user_id' => $actor->id,
                        'notes' => "Qo'lda muvofiqlashtirildi (Yo'qolgan qurilma). Sabab: {$reason}",
                    ]);
                } else {
                    $allocation->update(['status' => 'CLOSED']);
                }
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'DEVICE_CREDIT_RECONCILED',
                'auditable_type' => Device::class,
                'auditable_id' => $device->id,
                'new_values' => [
                    'device_id' => $device->id,
                    'reason' => $reason,
                ],
                'ip_address' => request()->ip(),
                'created_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Mijozning bo'sh kredit limiti (Online va Offline rezervlarni hisobga olgan holda).
     */
    public function getAvailableCreditLimit(Customer $customer, ?Device $callingDevice = null): int
    {
        $debtLimit = (int) $customer->debt_limit;
        if ($debtLimit <= 0) {
            return PHP_INT_MAX; // Limit belgilanmagan
        }

        $currentDebt = (int) $customer->current_debt;

        // Boshqa qurilmalarga ajratilgan faol rezervlar
        $query = CreditAllocation::where('customer_id', $customer->id)
            ->where('status', 'ACTIVE')
            ->where('is_new_customer_budget', false);

        if ($callingDevice) {
            // Agar so'rayotgan qurilma o'z ajratmasidan foydalanmoqchi bo'lsa, o'zining rezervini chetga surmaymiz
            $query->where('device_id', '!=', $callingDevice->id);
        }

        $otherDevicesReserved = (int) $query->sum(DB::raw('allocated_amount - consumed_amount - returned_amount'));

        return max(0, $debtLimit - $currentDebt - $otherDevicesReserved);
    }
}
