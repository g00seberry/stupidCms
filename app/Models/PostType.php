<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostType extends Model
{
    protected $guarded = [];
    protected $casts = ['options_json' => 'array'];

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }
}

