<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Action extends Model
{
    protected $fillable = [
        'research_task_id',
        'estimated_savings',
        'action_type',
        'approval_status',
    ];

    protected function casts(): array
    {
        return [
            'estimated_savings' => 'decimal:4',
        ];
    }

    public function researchTask(): BelongsTo
    {
        return $this->belongsTo(ResearchTask::class, 'research_task_id');
    }
}
