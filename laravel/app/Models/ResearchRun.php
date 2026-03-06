<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchRun extends Model
{
    protected $fillable = [
        'status',
        'batch_id',
        'limit',
        'use_claude',
        'build_queue',
        'message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'use_claude' => 'boolean',
            'build_queue' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
