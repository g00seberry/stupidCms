<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Taxonomy extends Model
{
    protected $guarded = [];

    public function terms()
    {
        return $this->hasMany(Term::class);
    }
}

