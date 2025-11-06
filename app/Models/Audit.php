<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    protected $guarded = [];
    protected $casts = ['diff_json' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

