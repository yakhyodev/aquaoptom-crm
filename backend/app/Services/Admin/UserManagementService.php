<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class UserManagementService
{
    /**
     * Foydalanuvchini faollashtirish yoki bloklash.
     */
    public function toggleUserStatus(User $targetUser, User $admin): User
    {
        if ($targetUser->isOwner()) {
            throw new InvalidArgumentException("Bosh do'kon egasini bloklash taqiqlanadi!");
        }

        if ($targetUser->id === $admin->id) {
            throw new InvalidArgumentException("Administrator o'z hisobini bloklay olmaydi!");
        }

        $oldStatus = $targetUser->status;
        $oldIsActive = $targetUser->is_active;

        $newIsActive = ! $oldIsActive;
        $newStatus = $newIsActive ? 'ACTIVE' : 'BLOCKED';

        $targetUser->is_active = $newIsActive;
        $targetUser->status = $newStatus;
        $targetUser->save();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'USER_STATUS_TOGGLED',
            'auditable_type' => User::class,
            'auditable_id' => $targetUser->id,
            'old_values' => ['status' => $oldStatus, 'is_active' => $oldIsActive],
            'new_values' => ['status' => $newStatus, 'is_active' => $newIsActive],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'created_at' => now(),
        ]);

        return $targetUser;
    }

    /**
     * Foydalanuvchi rolini o'zgartirish.
     */
    public function updateRole(User $targetUser, string $newRole, User $admin): User
    {
        if ($targetUser->isOwner()) {
            throw new InvalidArgumentException("Bosh do'kon egasining rolini o'zgartirish mumkin emas!");
        }

        $oldRole = $targetUser->role;
        if ($oldRole === $newRole) {
            return $targetUser;
        }

        $targetUser->role = $newRole;
        $targetUser->save();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'USER_ROLE_CHANGED',
            'auditable_type' => User::class,
            'auditable_id' => $targetUser->id,
            'old_values' => ['role' => $oldRole],
            'new_values' => ['role' => $newRole],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'created_at' => now(),
        ]);

        return $targetUser;
    }

    /**
     * Foydalanuvchiga aniq ruxsatni to'g'ridan-to'g'ri berish yoki olib qo'yish (Permission Override).
     */
    public function overridePermission(User $targetUser, string $permissionName, bool $isGranted, User $admin): void
    {
        if ($targetUser->isOwner()) {
            throw new InvalidArgumentException("Do'kon egasining huquqlarini cheklash mumkin emas!");
        }

        $permission = Permission::where('name', $permissionName)->firstOrFail();

        $existingOverride = $targetUser->directPermissions()
            ->where('permissions.id', $permission->id)
            ->first();

        $oldVal = $existingOverride ? (bool) $existingOverride->pivot->is_granted : null;

        $targetUser->givePermission($permissionName, $isGranted);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'USER_PERMISSION_OVERRIDDEN',
            'auditable_type' => User::class,
            'auditable_id' => $targetUser->id,
            'old_values' => ['permission' => $permissionName, 'is_granted' => $oldVal],
            'new_values' => ['permission' => $permissionName, 'is_granted' => $isGranted],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'created_at' => now(),
        ]);
    }

    /**
     * Foydalanuvchining to'g'ridan-to'g'ri ruxsat override'ini bekor qilish.
     */
    public function resetPermissionOverride(User $targetUser, string $permissionName, User $admin): void
    {
        $targetUser->revokePermission($permissionName);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'USER_PERMISSION_RESET',
            'auditable_type' => User::class,
            'auditable_id' => $targetUser->id,
            'old_values' => ['permission' => $permissionName],
            'new_values' => ['permission' => $permissionName, 'reset_to_role' => true],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'created_at' => now(),
        ]);
    }

    /**
     * Yangi xodim yaratish.
     */
    public function createUser(array $data, User $admin): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'status' => 'ACTIVE',
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'USER_CREATED',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => null,
            'new_values' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'created_at' => now(),
        ]);

        return $user;
    }

    /**
     * Foydalanuvchiga Telegram Chat ID biriktirish yoki uzish.
     */
    public function updateTelegramChatId(User $targetUser, ?int $chatId, ?string $username, User $admin): User
    {
        $oldChatId = $targetUser->telegram_chat_id;
        $targetUser->telegram_chat_id = $chatId;
        $targetUser->telegram_username = $username;
        $targetUser->save();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'USER_TELEGRAM_LINKED',
            'auditable_type' => User::class,
            'auditable_id' => $targetUser->id,
            'old_values' => ['telegram_chat_id' => $oldChatId],
            'new_values' => ['telegram_chat_id' => $chatId, 'telegram_username' => $username],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'created_at' => now(),
        ]);

        return $targetUser;
    }
}
