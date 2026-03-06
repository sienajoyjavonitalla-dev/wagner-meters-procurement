<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorPriority extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'data_import_id',
        'vendor_name',
        'priority_rank',
    ];

    protected function casts(): array
    {
        return [
            'priority_rank' => 'integer',
        ];
    }

    public function dataImport(): BelongsTo
    {
        return $this->belongsTo(DataImport::class, 'data_import_id');
    }
}
