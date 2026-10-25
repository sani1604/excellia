<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'academic_year_id');
    }

    public function teachingAllocations(): HasMany
    {
        return $this->hasMany(TeachingAllocation::class, 'academic_year_id');
    }

    public function documentTasks(): HasMany
    {
        return $this->hasMany(DocumentTask::class, 'academic_year_id');
    }

    public static function current(): ?self
    {
        return static::query()->where('is_current', true)->first();
    }
}
