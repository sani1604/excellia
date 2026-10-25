<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'employee_code',
        'full_name',
        'email',
        'phone',
        'password',
        'api_token_hash',
        'login_provider',
        'provider_subject_id',
        'primary_role_id',
        'can_access_restricted_files',
        'is_active',
        'deactivated_at',
        'deactivation_reason',
        'needs_reassignment_review',
        'last_login_at',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'api_token_hash',
        'remember_token',
    ];

    protected ?array $cachedPermissionKeys = null;
    protected ?array $cachedRoleKeys = null;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->email !== null) {
                $user->email = mb_strtolower(trim($user->email));
            }
            if ($user->employee_code !== null && trim($user->employee_code) === '') {
                $user->employee_code = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_access_restricted_files' => 'boolean',
            'is_active' => 'boolean',
            'needs_reassignment_review' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function primaryRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'primary_role_id');
    }

    public function secondaryRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['approved_by', 'created_at']);
    }

    public function scopeAssignments(): HasMany
    {
        return $this->hasMany(UserScopeAssignment::class, 'user_id');
    }

    public function activeScopeAssignments(): HasMany
    {
        return $this->scopeAssignments()
            ->where('is_active', true)
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today);
            })
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
            });
    }

    public function teachingAllocations(): HasMany
    {
        return $this->hasMany(TeachingAllocation::class, 'teacher_user_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(DocumentTask::class, 'assigned_teacher_id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'recipient_user_id');
    }

    public function roleKeys(): array
    {
        if ($this->cachedRoleKeys !== null) {
            return $this->cachedRoleKeys;
        }

        $keys = [];
        $primary = $this->relationLoaded('primaryRole') ? $this->primaryRole : $this->primaryRole()->first();
        if ($primary && $primary->is_active) {
            $keys[] = $primary->key;
        }

        $secondaries = $this->relationLoaded('secondaryRoles')
            ? $this->secondaryRoles
            : $this->secondaryRoles()->where('roles.is_active', true)->get();

        foreach ($secondaries as $role) {
            if ($role->is_active) {
                $keys[] = $role->key;
            }
        }

        return $this->cachedRoleKeys = array_values(array_unique($keys));
    }

    public function hasRole(string|array $roleKeys): bool
    {
        $targets = (array) $roleKeys;

        return count(array_intersect($this->roleKeys(), $targets)) > 0;
    }

    public function permissionKeys(): array
    {
        if ($this->cachedPermissionKeys !== null) {
            return $this->cachedPermissionKeys;
        }

        $roleIds = [$this->primary_role_id];
        $secondaryIds = $this->secondaryRoles()->where('roles.is_active', true)->pluck('roles.id')->all();
        $allRoleIds = array_values(array_unique(array_filter(array_merge($roleIds, $secondaryIds))));

        if (empty($allRoleIds)) {
            return $this->cachedPermissionKeys = [];
        }

        return $this->cachedPermissionKeys = Permission::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $allRoleIds)->where('roles.is_active', true))
            ->pluck('key')
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permissionKey): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return in_array($permissionKey, $this->permissionKeys(), true);
    }

    public function flushPermissionCache(): void
    {
        $this->cachedPermissionKeys = null;
        $this->cachedRoleKeys = null;
    }
}
