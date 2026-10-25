<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    public const SENSITIVITY_STANDARD = 'STANDARD';
    public const SENSITIVITY_RESTRICTED = 'RESTRICTED';
    public const SENSITIVITY_STRICTLY_RESTRICTED = 'STRICTLY_RESTRICTED';

    protected $fillable = [
        'code',
        'name',
        'description',
        'folder_code',
        'folder_name',
        'allowed_extensions',
        'max_file_size_mb',
        'sensitivity_level',
        'is_restricted',
        'access_policy_key',
        'default_reviewer_role_id',
        'default_due_rule',
        'requires_review',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'allowed_extensions' => 'array',
            'max_file_size_mb' => 'integer',
            'is_restricted' => 'boolean',
            'requires_review' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function defaultReviewerRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'default_reviewer_role_id');
    }

    public function documentTasks(): HasMany
    {
        return $this->hasMany(DocumentTask::class, 'document_type_id');
    }

    public function driveFolderMappings(): HasMany
    {
        return $this->hasMany(DriveFolderMapping::class, 'document_type_id');
    }

    public function normalizedExtensions(): array
    {
        return array_values(array_unique(array_map(
            fn ($ext) => ltrim(mb_strtolower(trim((string) $ext)), '.'),
            $this->allowed_extensions ?? []
        )));
    }
}
