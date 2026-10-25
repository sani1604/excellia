<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingAllocation extends Model
{
    protected $fillable = [
        'academic_year_id',
        'class_id',
        'section_id',
        'subject_id',
        'teacher_user_id',
        'is_class_teacher',
        'is_co_class_teacher',
        'allocation_start_date',
        'allocation_end_date',
        'is_active',
        'needs_reassignment',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_class_teacher' => 'boolean',
            'is_co_class_teacher' => 'boolean',
            'allocation_start_date' => 'date',
            'allocation_end_date' => 'date',
            'is_active' => 'boolean',
            'needs_reassignment' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documentTasks(): HasMany
    {
        return $this->hasMany(DocumentTask::class, 'teaching_allocation_id');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
