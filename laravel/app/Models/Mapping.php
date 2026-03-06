<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mapping extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'item_id',
        'mpn',
        'manufacturer',
        'mapping_status',
        'confidence',
        'notes',
        'lookup_mode',
        'data_import_id',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function dataImport(): BelongsTo
    {
        return $this->belongsTo(DataImport::class, 'data_import_id');
    }
}
