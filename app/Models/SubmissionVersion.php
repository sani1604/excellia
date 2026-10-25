<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubmissionVersion extends Model
{
    public const MODE_PORTAL_UPLOAD = 'PORTAL_UPLOAD';
    public const MODE_DRIVE_LINK = 'DRIVE_LINK';

    protected $fillable = [
        'document_submission_id',
        'version_number',
        'submission_mode',
        'drive_file_id',
        'drive_folder_id',
        'drive_file_name',
        'drive_file_url',
        'storage_disk_path',
        'mime_type',
        'file_extension',
        'file_size_bytes',
        'sha256_checksum',
        'reviewer_access_verified',
        'uploaded_by',
        'uploaded_at',
        'teacher_note',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size_bytes' => 'integer',
            'reviewer_access_verified' => 'boolean',
            'uploaded_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DocumentSubmission::class, 'document_submission_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SubmissionReview::class, 'submission_version_id')
            ->orderByDesc('reviewed_at');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(SubmissionReview::class, 'submission_version_id')
            ->latestOfMany('reviewed_at');
    }

    public function formattedSize(): string
    {
        $bytes = (int) $this->file_size_bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
