<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriveFolderMapping extends Model
{
    protected $fillable = [
        'academic_year_id',
        'class_id',
        'section_id',
        'subject_id',
        'teacher_user_id',
        'document_type_id',
        'drive_folder_id',
        'drive_folder_name',
        'drive_folder_url',
        'access_policy_key',
        'is_active',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
