<?php

namespace App\Livewire\Devices;

use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Devices\DeviceService;
use App\Services\Devices\OfflineLeaseService;
use App\Services\Ledger\CreditAllocationService;
use App\Services\Ledger\InventoryAllocationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class DeviceManager extends Component
{
    // Modals
    public bool $showRegisterModal = false;

    public bool $showLeaseModal = false;

    public bool $showStockAllocModal = false;

    public bool $showCreditAllocModal = false;

    public bool $showReconcileModal = false;

    // Messages
    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    // Register Device form
    public string $newDeviceName = '';

    public string $newDeviceType = 'PC';

    public ?int $newDeviceUserId = null;

    public bool $allowNewCustDebt = false;

    public int $newCustDebtBudget = 0;

    public string $newDeviceNotes = '';

    // Issue Lease form
    public ?int $selectedDeviceId = null;

    public ?int $leaseUserId = null;

    public int $leaseDurationHours = 24;

    public array $leasePermissions = ['offline_sales', 'sell_on_credit'];

    // Stock Allocation form
    public ?int $stockAllocDeviceId = null;

    public ?int $stockAllocVariantId = null;

    public int $stockAllocQuantity = 1;

    public string $stockAllocNotes = '';

    // Credit Allocation form
    public ?int $creditAllocDeviceId = null;

    public ?int $creditAllocCustomerId = null;

    public bool $creditIsNewCustBudget = false;

    public int $creditAllocAmount = 0;

    public string $creditAllocNotes = '';

    // Reconcile / Lost form
    public ?int $reconcileDeviceId = null;

    public string $reconcileReason = '';

    public function mount(): void
    {
        $this->newDeviceUserId = Auth::id();
        $this->leaseUserId = Auth::id();
    }

    public function openRegisterModal(): void
    {
        $this->reset(['newDeviceName', 'newDeviceType', 'allowNewCustDebt', 'newCustDebtBudget', 'newDeviceNotes', 'errorMessage', 'feedbackMessage']);
        $this->newDeviceType = 'PC';
        $this->newDeviceUserId = Auth::id();
        $this->showRegisterModal = true;
    }

    public function registerDevice(DeviceService $deviceService): void
    {
        $this->reset(['errorMessage', 'feedbackMessage']);

        $this->validate([
            'newDeviceName' => 'required|min:2|max:100',
            'newDeviceType' => 'required|in:PC,MOBILE,TABLET,POS_TERMINAL,OTHER',
            'newCustDebtBudget' => 'numeric|min:0',
        ]);

        try {
            $device = $deviceService->registerDevice([
                'name' => $this->newDeviceName,
                'device_type' => $this->newDeviceType,
                'assigned_user_id' => $this->newDeviceUserId,
                'allow_new_offline_customer_debt' => $this->allowNewCustDebt,
                'new_customer_debt_budget' => $this->newCustDebtBudget,
                'notes' => $this->newDeviceNotes,
            ], Auth::user());

            $this->showRegisterModal = false;
            $this->feedbackMessage = "Qurilma muvaffaqiyatli ro'yxatdan o'tkazildi! Kodi: {$device->device_code}";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openLeaseModal(int $deviceId): void
    {
        $this->reset(['errorMessage', 'feedbackMessage']);
        $this->selectedDeviceId = $deviceId;
        $device = Device::find($deviceId);
        $this->leaseUserId = $device->assigned_user_id ?: Auth::id();
        $this->leaseDurationHours = 24;
        $this->leasePermissions = ['offline_sales', 'sell_on_credit'];
        $this->showLeaseModal = true;
    }

    public function issueLease(OfflineLeaseService $leaseService): void
    {
        $this->reset(['errorMessage', 'feedbackMessage']);

        $device = Device::find($this->selectedDeviceId);
        $user = User::find($this->leaseUserId);

        if (! $device || ! $user) {
            $this->errorMessage = "Qurilma yoki mas'ul xodim topilmadi!";

            return;
        }

        try {
            $lease = $leaseService->issueLease(
                device: $device,
                user: $user,
                permissions: $this->leasePermissions,
                durationHours: $this->leaseDurationHours,
                actor: Auth::user()
            );

            $this->showLeaseModal = false;
            $this->feedbackMessage = "Qurilmaga imzolangan ruxsat (lease) muvaffaqiyatli berildi! Amal qilish muddati: {$this->leaseDurationHours} soat.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function revokeLease(int $deviceId, OfflineLeaseService $leaseService): void
    {
        $device = Device::find($deviceId);
        if (! $device) {
            return;
        }

        $activeLease = $device->activeAuthorization;
        if (! $activeLease) {
            $this->errorMessage = 'Qurilmada faol ruxsat guvohnomasi mavjud emas.';

            return;
        }

        try {
            $leaseService->revokeLease($activeLease, Auth::user(), 'Admin tomonidan bekor qilindi');
            $this->feedbackMessage = "Qurilmaning (#{$device->device_code}) ruxsat guvohnomasi bekor qilindi.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openStockAllocModal(int $deviceId): void
    {
        $this->reset(['errorMessage', 'feedbackMessage']);
        $this->stockAllocDeviceId = $deviceId;
        $this->stockAllocVariantId = ProductVariant::first()?->id;
        $this->stockAllocQuantity = 10;
        $this->stockAllocNotes = '';
        $this->showStockAllocModal = true;
    }

    public function grantStockAllocation(InventoryAllocationService $allocService): void
    {
        $this->reset(['errorMessage', 'feedbackMessage']);

        $this->validate([
            'stockAllocDeviceId' => 'required|exists:devices,id',
            'stockAllocVariantId' => 'required|exists:product_variants,id',
            'stockAllocQuantity' => 'required|integer|min:1',
        ]);

        $device = Device::find($this->stockAllocDeviceId);

        try {
            $alloc = $allocService->grantAllocation(
                device: $device,
                variantId: $this->stockAllocVariantId,
                quantity: $this->stockAllocQuantity,
                operationId: (string) Str::uuid(),
                userId: Auth::id(),
                notes: $this->stockAllocNotes ?: null
            );

            $this->showStockAllocModal = false;
            $this->feedbackMessage = "{$this->stockAllocQuantity} dona tovar qurilmaga (#{$device->device_code}) muvaffaqiyatli ajratildi!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function returnStockAllocation(int $allocationId, InventoryAllocationService $allocService): void
    {
        $alloc = InventoryAllocation::find($allocationId);
        if (! $alloc) {
            return;
        }

        $avail = $alloc->available_quantity;
        if ($avail <= 0) {
            $this->errorMessage = "Qurilmada qaytarish uchun bo'sh ajratma yo'q.";

            return;
        }

        try {
            $allocService->returnAllocation(
                device: $alloc->device,
                variantId: $alloc->product_variant_id,
                quantity: $avail,
                operationId: (string) Str::uuid(),
                userId: Auth::id(),
                notes: "Admin tomonidan to'liq omborga qaytarildi"
            );

            $this->feedbackMessage = "{$avail} dona tovar omborga erkin qoldiq sifatida qaytarildi!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openCreditAllocModal(int $deviceId): void
    {
        $this->reset(['errorMessage', 'feedbackMessage']);
        $this->creditAllocDeviceId = $deviceId;
        $this->creditAllocCustomerId = Customer::where('debt_limit', '>', 0)->first()?->id ?: Customer::first()?->id;
        $this->creditIsNewCustBudget = false;
        $this->creditAllocAmount = 500000;
        $this->creditAllocNotes = '';
        $this->showCreditAllocModal = true;
    }

    public function grantCreditAllocation(CreditAllocationService $creditService): void
    {
        $this->reset(['errorMessage', 'feedbackMessage']);

        $this->validate([
            'creditAllocDeviceId' => 'required|exists:devices,id',
            'creditAllocAmount' => 'required|integer|min:1',
        ]);

        $device = Device::find($this->creditAllocDeviceId);
        $customer = $this->creditIsNewCustBudget ? null : Customer::find($this->creditAllocCustomerId);

        try {
            $creditService->grantCreditAllocation(
                device: $device,
                customer: $customer,
                amount: $this->creditAllocAmount,
                isNewCustomerBudget: $this->creditIsNewCustBudget,
                operationId: (string) Str::uuid(),
                userId: Auth::id(),
                notes: $this->creditAllocNotes ?: null
            );

            $this->showCreditAllocModal = false;
            $this->feedbackMessage = 'Kredit limiti ('.number_format($this->creditAllocAmount, 0, '.', ' ')." so'm) qurilmaga muvaffaqiyatli ajratildi!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openReconcileModal(int $deviceId): void
    {
        $this->reset(['errorMessage', 'feedbackMessage']);
        $this->reconcileDeviceId = $deviceId;
        $this->reconcileReason = 'Qurilma yo\'qolgan yoki aloqadan uzilgan';
        $this->showReconcileModal = true;
    }

    public function reconcileLostDevice(
        DeviceService $deviceService,
        InventoryAllocationService $allocService,
        CreditAllocationService $creditService
    ): void {
        $this->reset(['errorMessage', 'feedbackMessage']);

        $device = Device::find($this->reconcileDeviceId);
        if (! $device) {
            return;
        }

        try {
            // 1. Mark device lost
            $deviceService->markDeviceLost($device, Auth::user(), $this->reconcileReason);

            // 2. Manual reconcile stock allocations
            $allocService->manualReconcileLostDevice($device, Auth::user(), $this->reconcileReason);

            // 3. Manual reconcile credit allocations
            $creditService->manualReconcileLostDeviceCredit($device, Auth::user(), $this->reconcileReason);

            $this->showReconcileModal = false;
            $this->feedbackMessage = "Qurilma (#{$device->device_code}) yo'qolgan deb belgilandi va barcha rezervlari omborga qaytarilib muvofiqlashtirildi!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $devices = Device::with(['assignedUser', 'activeAuthorization.user', 'activeInventoryAllocations.productVariant.product', 'activeInventoryAllocations.productVariant.volume', 'activeCreditAllocations.customer'])
            ->latest('last_seen_at')
            ->get();

        $users = User::where('is_active', true)->orderBy('name')->get();
        $customers = Customer::orderBy('name')->get();
        $variants = ProductVariant::with(['product', 'volume', 'balance'])->get();

        // Ombor qoldig'i va rezervlar xulosasi (birinchi 5 ta variant bo'yicha)
        $stockBreakdown = [];
        foreach ($variants->take(5) as $v) {
            $totalOnHand = $v->balance ? (int) $v->balance->quantity : 0;
            $totalReserved = (int) InventoryAllocation::where('product_variant_id', $v->id)
                ->where('status', 'ACTIVE')
                ->sum(DB::raw('allocated_quantity - consumed_quantity - returned_quantity'));
            $freeStock = max(0, $totalOnHand - $totalReserved);

            $stockBreakdown[] = [
                'variant' => $v,
                'total_on_hand' => $totalOnHand,
                'total_reserved' => $totalReserved,
                'free_stock' => $freeStock,
            ];
        }

        return view('livewire.devices.device-manager', [
            'devices' => $devices,
            'users' => $users,
            'customers' => $customers,
            'variants' => $variants,
            'stockBreakdown' => $stockBreakdown,
        ]);
    }
}
