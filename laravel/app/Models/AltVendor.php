<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AltVendor extends Model
{
    protected $fillable = [
        'mpn_id',
        'vendor_name',
        'unit_price',
        'url',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'fetched_at' => 'datetime',
        ];
    }

    public function mpn(): BelongsTo
    {
        return $this->belongsTo(Mpn::class);
    }
}
