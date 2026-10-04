<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'is_active',
        'phone',
        'current_store_id',
        'telegram_chat_id',
        'telegram_username',
    ];

    /**
     * Telegram chat ID orqali xodimni topish
     */
    public static function findByTelegramChatId(int|string|null $chatId): ?self
    {
        if (! $chatId) {
            return null;
        }

        return static::where('telegram_chat_id', $chatId)->first();
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if user is the Store Owner.
     */
    public function isOwner(): bool
    {
        return $this->role === 'OWNER';
    }

    /**
     * Check if user is an Administrator or Owner.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['OWNER', 'ADMIN'], true);
    }

    /**
     * Check if user is active and not blocked.
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->status === 'ACTIVE';
    }

    /**
     * Check if user has a specific role or any of the given roles.
     *
     * @param  string|array<string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $roleList = is_array($roles) ? $roles : [$roles];

        return in_array($this->role, $roleList, true);
    }

    /**
     * Check if user has a specific permission.
     * Owner has all permissions unconditionally.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        if (! $this->isActive()) {
            return false;
        }

        // 1. Check direct user permission override
        $override = $this->directPermissions()
            ->where('name', $permission)
            ->first();

        if ($override) {
            return (bool) $override->pivot->is_granted;
        }

        // 2. Check role permissions
        return $this->roleModel?->permissions()->where('name', $permission)->exists() ?? false;
    }

    /**
     * Get all active permissions for the user as string array.
     */
    public function getAllPermissions(): array
    {
        if ($this->isOwner()) {
            return Permission::pluck('name')->toArray();
        }

        $rolePermissions = $this->roleModel?->permissions()->pluck('name')->toArray() ?? [];
        $overrides = $this->directPermissions()->get();

        foreach ($overrides as $override) {
            if ($override->pivot->is_granted) {
                if (! in_array($override->name, $rolePermissions, true)) {
                    $rolePermissions[] = $override->name;
                }
            } else {
                $rolePermissions = array_values(array_diff($rolePermissions, [$override->name]));
            }
        }

        return $rolePermissions;
    }

    /**
     * Give or override a direct permission for this user.
     */
    public function givePermission(string $permissionName, bool $isGranted = true): void
    {
        $permission = Permission::where('name', $permissionName)->firstOrFail();
        $this->directPermissions()->syncWithoutDetaching([
            $permission->id => ['is_granted' => $isGranted],
        ]);
    }

    /**
     * Revoke a direct permission override.
     */
    public function revokePermission(string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)->first();
        if ($permission) {
            $this->directPermissions()->detach($permission->id);
        }
    }

    /**
     * Role model relation.
     */
    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'name');
    }

    /**
     * Direct user permissions relation.
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot('is_granted');
    }

    public function assignedDevices(): HasMany
    {
        return $this->hasMany(Device::class, 'assigned_user_id');
    }

    public function deviceAuthorizations(): HasMany
    {
        return $this->hasMany(OfflineAuthorization::class);
    }
}
