<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SyncConflict;
use App\Models\User;
use App\Services\Admin\SystemSettingsService;
use App\Services\Admin\TelemetryService;
use App\Services\Admin\UserManagementService;
use App\Services\Sync\SyncConflictResolutionService;
use Livewire\Component;
use Livewire\WithPagination;

class AdminPanel extends Component
{
    use WithPagination;

    public string $activeTab = 'settings';

    // Settings Tab Properties
    public string $storeName = '';

    public string $storePhone = '';

    public string $storeAddress = '';

    public string $timezone = 'Asia/Tashkent';

    public int $lowStockThreshold = 10;

    public bool $strictCreditMode = false;

    public int $offlineReconcileTimeoutHours = 24;

    public string $settingsSuccessMessage = '';

    // Users Tab Properties
    public ?int $selectedUserId = null;

    public string $selectedUserRole = '';

    public bool $showPermissionModal = false;

    public ?int $permissionTargetUserId = null;

    public array $userPermissionOverrides = [];

    // Add User Modal
    public bool $showAddUserModal = false;

    public string $newUserName = '';

    public string $newUserEmail = '';

    public string $newUserPhone = '';

    public string $newUserRole = 'SALES_MANAGER';

    public string $newUserPassword = '';

    // Conflict Resolution Modal
    public bool $showConflictModal = false;

    public ?int $selectedConflictId = null;

    public string $conflictAction = 'APPROVED_OVERRIDE';

    public string $conflictReason = '';

    public string $conflictErrorMessage = '';

    // Audit Log Filters
    public string $auditActionFilter = '';

    public ?int $auditUserFilter = null;

    public function mount(SystemSettingsService $settingsService): void
    {
        $this->loadSettings($settingsService);
    }

    public function loadSettings(SystemSettingsService $settingsService): void
    {
        $settings = $settingsService->getAll();
        $this->storeName = (string) ($settings['store_name'] ?? "AquaOptom Suv Do'koni");
        $this->storePhone = (string) ($settings['store_phone'] ?? '+998712000000');
        $this->storeAddress = (string) ($settings['store_address'] ?? 'Toshkent shahri, Chilonzor tumani');
        $this->timezone = (string) ($settings['timezone'] ?? 'Asia/Tashkent');
        $this->lowStockThreshold = (int) ($settings['low_stock_threshold'] ?? 10);
        $this->strictCreditMode = (bool) ($settings['strict_credit_mode'] ?? false);
        $this->offlineReconcileTimeoutHours = (int) ($settings['offline_reconcile_timeout_hours'] ?? 24);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->settingsSuccessMessage = '';
        $this->resetPage();
    }

    /**
     * Sozlamalarni saqlash.
     */
    public function saveSettings(SystemSettingsService $settingsService): void
    {
        $this->validate([
            'storeName' => 'required|string|max:150',
            'storePhone' => 'required|string|max:30',
            'storeAddress' => 'required|string|max:255',
            'timezone' => 'required|string|max:64',
            'lowStockThreshold' => 'required|integer|min:0|max:1000',
            'offlineReconcileTimeoutHours' => 'required|integer|min:1|max:168',
        ]);

        $settingsService->updateSettings([
            'store_name' => $this->storeName,
            'store_phone' => $this->storePhone,
            'store_address' => $this->storeAddress,
            'timezone' => $this->timezone,
            'low_stock_threshold' => $this->lowStockThreshold,
            'strict_credit_mode' => $this->strictCreditMode,
            'offline_reconcile_timeout_hours' => $this->offlineReconcileTimeoutHours,
        ], auth()->user());

        $this->settingsSuccessMessage = 'Tizim sozlamalari muvaffaqiyatli saqlandi va auditda qayd etildi!';
    }

    /**
     * Foydalanuvchi holatini (Faol/Bloklangan) o'zgartirish.
     */
    public function toggleUserStatus(int $userId, UserManagementService $userService): void
    {
        $targetUser = User::findOrFail($userId);
        try {
            $userService->toggleUserStatus($targetUser, auth()->user());
            session()->flash('user_message', "Foydalanuvchi holati yangilandi ({$targetUser->fresh()->status}).");
        } catch (\Throwable $e) {
            session()->flash('user_error', $e->getMessage());
        }
    }

    /**
     * Foydalanuvchi rolini o'zgartirish.
     */
    public function updateUserRole(int $userId, string $newRole, UserManagementService $userService): void
    {
        $targetUser = User::findOrFail($userId);
        try {
            $userService->updateRole($targetUser, $newRole, auth()->user());
            session()->flash('user_message', "Foydalanuvchi roli {$newRole} ga o'zgartirildi.");
        } catch (\Throwable $e) {
            session()->flash('user_error', $e->getMessage());
        }
    }

    /**
     * Foydalanuvchi ruxsatlarini tahrirlash modalini ochish.
     */
    public function openPermissionModal(int $userId): void
    {
        $this->permissionTargetUserId = $userId;
        $user = User::with('directPermissions')->findOrFail($userId);

        $overrides = [];
        foreach ($user->directPermissions as $dp) {
            $overrides[$dp->name] = (bool) $dp->pivot->is_granted;
        }

        $this->userPermissionOverrides = $overrides;
        $this->showPermissionModal = true;
    }

    public function closePermissionModal(): void
    {
        $this->showPermissionModal = false;
        $this->permissionTargetUserId = null;
        $this->userPermissionOverrides = [];
    }

    /**
     * To'g'ridan-to'g'ri ruxsatni yoqish / o'chirish (Grant / Revoke / Reset).
     */
    public function setPermissionOverride(string $permissionName, ?bool $isGranted, UserManagementService $userService): void
    {
        if (! $this->permissionTargetUserId) {
            return;
        }

        $targetUser = User::findOrFail($this->permissionTargetUserId);

        if ($isGranted === null) {
            $userService->resetPermissionOverride($targetUser, $permissionName, auth()->user());
            unset($this->userPermissionOverrides[$permissionName]);
        } else {
            $userService->overridePermission($targetUser, $permissionName, $isGranted, auth()->user());
            $this->userPermissionOverrides[$permissionName] = $isGranted;
        }
    }

    /**
     * Yangi xodim qo'shish.
     */
    public function createUser(UserManagementService $userService): void
    {
        $this->validate([
            'newUserName' => 'required|string|max:100',
            'newUserEmail' => 'required|email|unique:users,email',
            'newUserPhone' => 'nullable|string|max:30',
            'newUserRole' => 'required|string',
            'newUserPassword' => 'required|string|min:8',
        ]);

        $userService->createUser([
            'name' => $this->newUserName,
            'email' => $this->newUserEmail,
            'phone' => $this->newUserPhone,
            'role' => $this->newUserRole,
            'password' => $this->newUserPassword,
        ], auth()->user());

        $this->showAddUserModal = false;
        $this->newUserName = '';
        $this->newUserEmail = '';
        $this->newUserPhone = '';
        $this->newUserPassword = '';

        session()->flash('user_message', "Yangi xodim muvaffaqiyatli qo'shildi!");
    }

    /**
     * NEEDS_REVIEW mojaroni hal qilish modalini ochish.
     */
    public function openConflictModal(int $conflictId): void
    {
        $this->selectedConflictId = $conflictId;
        $this->conflictAction = 'APPROVED_OVERRIDE';
        $this->conflictReason = '';
        $this->conflictErrorMessage = '';
        $this->showConflictModal = true;
    }

    public function closeConflictModal(): void
    {
        $this->showConflictModal = false;
        $this->selectedConflictId = null;
        $this->conflictReason = '';
        $this->conflictErrorMessage = '';
    }

    /**
     * NEEDS_REVIEW mojaroni hal qilish (Sababli resolution).
     */
    public function resolveConflict(SyncConflictResolutionService $resolutionService): void
    {
        if (! $this->selectedConflictId) {
            return;
        }

        $this->validate([
            'conflictAction' => 'required|in:APPROVED_OVERRIDE,REJECT',
            'conflictReason' => 'required|string|min:5|max:500',
        ]);

        $conflict = SyncConflict::findOrFail($this->selectedConflictId);

        try {
            $resolutionService->resolveConflict(
                conflict: $conflict,
                admin: auth()->user(),
                action: $this->conflictAction,
                overrideData: [],
                reason: $this->conflictReason
            );

            $this->closeConflictModal();
            session()->flash('conflict_message', "Mojaro muvaffaqiyatli hal qilindi ({$this->conflictAction}) va ombor/kassa/mijoz balansi yangilandi!");
        } catch (\Throwable $e) {
            $this->conflictErrorMessage = $e->getMessage();
        }
    }

    public function render(TelemetryService $telemetryService)
    {
        $users = User::with(['roleModel', 'directPermissions'])->latest()->get();
        $allPermissions = Permission::orderBy('category')->orderBy('name')->get();
        $roles = Role::with('permissions')->get();

        $conflictsQuery = SyncConflict::with(['device', 'user', 'resolver'])->latest();
        $conflicts = $conflictsQuery->paginate(15);

        $auditLogsQuery = AuditLog::with('user')->latest('created_at');
        if ($this->auditActionFilter) {
            $auditLogsQuery->where('action', 'like', "%{$this->auditActionFilter}%");
        }
        if ($this->auditUserFilter) {
            $auditLogsQuery->where('user_id', $this->auditUserFilter);
        }
        $auditLogs = $auditLogsQuery->paginate(20);

        $telemetry = $telemetryService->getTelemetry();

        return view('livewire.admin.admin-panel', [
            'users' => $users,
            'allPermissions' => $allPermissions,
            'roles' => $roles,
            'conflicts' => $conflicts,
            'auditLogs' => $auditLogs,
            'telemetry' => $telemetry,
        ]);
    }
}
