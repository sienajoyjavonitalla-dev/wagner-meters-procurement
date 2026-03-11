<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $fillable = [
        'data_import_id',
        'transaction_date',
        'item_id',
        'description',
        'fiscal_period',
        'fiscal_year',
        'reference_id',
        'location_id',
        'source_id',
        'type',
        'application_id',
        'unit',
        'quantity',
        'unit_cost',
        'ext_cost',
        'comments',
        'product_line',
        'vendor_name',
        'contact',
        'address',
        'region',
        'phone',
        'email',
        'research_completed_at',
        'gemini_response_json',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'ext_cost' => 'decimal:4',
            'research_completed_at' => 'datetime',
            'gemini_response_json' => 'array',
        ];
    }

    public function dataImport(): BelongsTo
    {
        return $this->belongsTo(DataImport::class, 'data_import_id');
    }

    public function mpns(): HasMany
    {
        return $this->hasMany(Mpn::class);
    }

    public function altVendors(): HasMany
    {
        return $this->hasMany(AltVendor::class, 'inventory_id');
    }
}
