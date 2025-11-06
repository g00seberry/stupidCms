<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Outbox extends Model
{
    protected $guarded = [];
    protected $casts = [
        'payload_json' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
    ];
}

