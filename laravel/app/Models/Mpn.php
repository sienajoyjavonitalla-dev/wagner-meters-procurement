<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mpn extends Model
{
    protected $table = 'mpn';

    protected $fillable = [
        'inventory_id',
        'part_number',
        'unit_price',
        'price_fetched_at',
        'currency',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'price_fetched_at' => 'datetime',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function altVendors(): HasMany
    {
        return $this->hasMany(AltVendor::class);
    }
}
