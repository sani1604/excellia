<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionReview extends Model
{
    public const DECISION_APPROVED = 'APPROVED';
    public const DECISION_RETURNED = 'RETURNED';

    protected $fillable = [
        'submission_version_id',
        'reviewer_user_id',
        'decision',
        'remarks',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SubmissionVersion::class, 'submission_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
