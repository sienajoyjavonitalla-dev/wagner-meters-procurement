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
        'use_gemini',
        'build_queue',
        'message',
        'gemini_hits',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'use_claude' => 'boolean',
            'use_gemini' => 'boolean',
            'build_queue' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
