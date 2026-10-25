<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSubmission extends Model
{
    protected $fillable = [
        'document_task_id',
        'current_version_id',
        'current_status',
        'submitted_at',
        'last_submitted_at',
        'approved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'last_submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DocumentTask::class, 'document_task_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SubmissionVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SubmissionVersion::class, 'document_submission_id')
            ->orderByDesc('version_number');
    }
}
