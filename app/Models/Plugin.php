<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    use HasUlids;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'meta_json' => 'array',
        'last_synced_at' => 'immutable_datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;
}

