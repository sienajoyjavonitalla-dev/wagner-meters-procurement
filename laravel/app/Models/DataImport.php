<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataImport extends Model
{
    public function scopeCurrentFull(Builder $query): Builder
    {
        return $query->where('type', 'full')
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->limit(1);
    }
    protected $fillable = [
        'type',
        'user_id',
        'file_names',
        'row_counts',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'file_names' => 'array',
            'row_counts' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'data_import_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'data_import_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'data_import_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(Mapping::class, 'data_import_id');
    }

    public function vendorPriorities(): HasMany
    {
        return $this->hasMany(VendorPriority::class, 'data_import_id');
    }

    public function itemSpreads(): HasMany
    {
        return $this->hasMany(ItemSpread::class, 'data_import_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'data_import_id');
    }
}
