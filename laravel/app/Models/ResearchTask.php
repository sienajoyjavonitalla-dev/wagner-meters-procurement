<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResearchTask extends Model
{
    protected $fillable = [
        'task_type',
        'item_id',
        'supplier_id',
        'status',
        'priority',
        'batch_id',
        'notes',
        'description',
        'spend_12m',
        'qty_12m',
        'avg_unit_cost_12m',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'spend_12m' => 'decimal:4',
            'qty_12m' => 'decimal:4',
            'avg_unit_cost_12m' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function priceFindings(): HasMany
    {
        return $this->hasMany(PriceFinding::class, 'research_task_id');
    }

    public function action(): HasOne
    {
        return $this->hasOne(Action::class, 'research_task_id');
    }
}
