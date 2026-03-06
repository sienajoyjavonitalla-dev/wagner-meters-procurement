<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceFinding extends Model
{
    protected $fillable = [
        'research_task_id',
        'provider',
        'currency',
        'price_breaks_json',
        'min_unit_price',
        'match_score',
        'accepted',
    ];

    protected function casts(): array
    {
        return [
            'price_breaks_json' => 'array',
            'min_unit_price' => 'decimal:4',
            'match_score' => 'decimal:2',
            'accepted' => 'boolean',
        ];
    }

    public function researchTask(): BelongsTo
    {
        return $this->belongsTo(ResearchTask::class, 'research_task_id');
    }
}
