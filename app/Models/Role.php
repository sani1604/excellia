<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const TEACHER = 'TEACHER';
    public const COORDINATOR = 'COORDINATOR';
    public const HEADMISTRESS = 'HEADMISTRESS';
    public const PRINCIPAL = 'PRINCIPAL';
    public const DIRECTOR = 'DIRECTOR';
    public const VIEWER = 'VIEWER';

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_system_role',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system_role' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'primary_role_id');
    }
}
