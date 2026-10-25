<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'code',
        'name',
        'display_order',
        'stage',
        'drive_folder_label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_id');
    }

    public function teachingAllocations(): HasMany
    {
        return $this->hasMany(TeachingAllocation::class, 'class_id');
    }
}
