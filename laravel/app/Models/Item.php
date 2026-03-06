<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'internal_part_number',
        'description',
        'category',
        'lifecycle_status',
        'data_import_id',
    ];

    public function dataImport(): BelongsTo
    {
        return $this->belongsTo(DataImport::class, 'data_import_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(Mapping::class);
    }

    public function researchTasks(): HasMany
    {
        return $this->hasMany(ResearchTask::class);
    }
}
