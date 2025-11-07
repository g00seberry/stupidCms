<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    protected $table = 'options';
    protected $fillable = ['namespace', 'key', 'value_json'];
    protected $casts = [
        'value_json' => 'json', // хранит любой JSON
    ];
}

