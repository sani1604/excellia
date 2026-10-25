<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScopeAssignment extends Model
{
    public const SCOPE_SCHOOL = 'SCHOOL';
    public const SCOPE_STAGE = 'STAGE';
    public const SCOPE_CLASS = 'CLASS';
    public const SCOPE_SECTION = 'SECTION';
    public const SCOPE_SUBJECT = 'SUBJECT';
    public const SCOPE_TEACHER = 'TEACHER';

    protected $fillable = [
        'user_id',
        'academic_year_id',
        'scope_type',
        'scope_id',
        'scope_value',
        'can_review',
        'can_access_restricted',
        'effective_from',
        'effective_to',
        'is_active',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'can_review' => 'boolean',
            'can_access_restricted' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
