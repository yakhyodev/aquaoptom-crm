<?php

namespace App\Services\Sync;

use App\Models\Device;
use App\Models\DeviceCursor;
use App\Models\SyncChangeLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Devices\OfflineLeaseService;
use Carbon\Carbon;

class SyncBootstrapService
{
    public function __construct(
        protected OfflineLeaseService $leaseService,
        protected SyncChangeLogService $changeLogService
    ) {}

    /**
     * Qurilma uchun to'liq boshlang'ich konfiguratsiya va ruxsatlar snapshoti (Device Bootstrap).
     */
    public function getBootstrapData(Device $device, User $user): array
    {
        // 1. Agar change log bo'sh bo'lsa, mavjud katalog ma'lumotlarini yuklaymiz
        if (SyncChangeLog::count() === 0) {
            $this->changeLogService->populateInitialChanges();
        }

        // 2. Faol Lease (Snapshot)
        $activeAuth = $device->activeAuthorization;
        $leaseData = null;

        if ($activeAuth && $activeAuth->isValid()) {
            $leaseData = [
                'lease_token' => $activeAuth->lease_token,
                'valid_from' => $activeAuth->valid_from->format('Y-m-d\TH:i:s\Z'),
                'expires_at' => $activeAuth->expires_at->format('Y-m-d\TH:i:s\Z'),
                'permissions' => $activeAuth->permissions,
                'epoch' => $activeAuth->epoch,
                'signature' => $activeAuth->signature,
            ];
        } else {
            // Agar faol lease bo'lmasa, yangi 24 soatlik lease chiqarish
            $leaseBundle = $this->leaseService->issueLease(
                device: $device,
                user: $user,
                durationHours: 24,
                actor: $user
            );

            $leaseData = [
                'lease_token' => $leaseBundle['lease_token'],
                'valid_from' => $leaseBundle['valid_from'],
                'expires_at' => $leaseBundle['expires_at'],
                'permissions' => $leaseBundle['permissions'],
                'epoch' => $leaseBundle['epoch'],
                'signature' => $leaseBundle['signature'],
            ];
        }

        // 3. Ombor
        $defaultWarehouse = Warehouse::where('is_default', true)->first() ?: Warehouse::first();

        // 4. Faol tovar ajratmalari (Stock Allocations)
        $stockAllocations = $device->activeInventoryAllocations()
            ->with(['variant.product', 'variant.volume'])
            ->get()
            ->map(function ($alloc) {
                return [
                    'allocation_id' => $alloc->id,
                    'variant_id' => $alloc->product_variant_id,
                    'sku' => $alloc->variant?->sku,
                    'product_name' => $alloc->variant?->product?->name,
                    'volume_name' => $alloc->variant?->volume?->name,
                    'allocated_quantity' => (int) $alloc->allocated_quantity,
                    'consumed_quantity' => (int) $alloc->consumed_quantity,
                    'returned_quantity' => (int) $alloc->returned_quantity,
                    'available_quantity' => (int) $alloc->available_quantity,
                ];
            })->values()->all();

        // 5. Faol mijoz kredit ajratmalari (Credit Allocations)
        $creditAllocations = $device->activeCreditAllocations()
            ->with('customer')
            ->get()
            ->map(function ($alloc) {
                return [
                    'allocation_id' => $alloc->id,
                    'customer_id' => $alloc->customer_id,
                    'customer_name' => $alloc->customer?->name,
                    'allocated_amount' => (int) $alloc->allocated_amount,
                    'consumed_amount' => (int) $alloc->consumed_amount,
                    'returned_amount' => (int) $alloc->returned_amount,
                    'available_amount' => (int) $alloc->available_amount,
                ];
            })->values()->all();

        // 6. Serverdagi joriy kursor
        $latestCursor = (int) (SyncChangeLog::max('id') ?: 0);

        // Qurilma kursorini yangilash
        DeviceCursor::updateOrCreate(
            ['device_id' => $device->id],
            [
                'last_cursor' => $latestCursor,
                'last_pulled_at' => Carbon::now(),
            ]
        );

        // Heartbeat yangilash
        $device->update([
            'last_seen_at' => Carbon::now(),
            'last_ip_address' => request()->ip(),
        ]);

        return [
            'device' => [
                'id' => $device->id,
                'device_code' => $device->device_code,
                'device_uuid' => $device->device_uuid,
                'name' => $device->name,
                'device_type' => $device->device_type,
                'status' => $device->status,
                'is_active' => $device->is_active,
                'current_lease_epoch' => (int) $device->current_lease_epoch,
                'allow_new_offline_customer_debt' => (bool) $device->allow_new_offline_customer_debt,
                'new_customer_debt_budget' => (int) $device->new_customer_debt_budget,
            ],
            'lease' => $leaseData,
            'warehouse' => [
                'id' => $defaultWarehouse?->id,
                'name' => $defaultWarehouse?->name,
            ],
            'stock_allocations' => $stockAllocations,
            'credit_allocations' => $creditAllocations,
            'current_cursor' => $latestCursor,
            'server_time' => Carbon::now()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
