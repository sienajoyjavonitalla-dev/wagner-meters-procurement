<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }
}
