<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermTree extends Model
{
    public $timestamps = false;
    protected $table = 'term_tree';
    protected $guarded = [];
    public $incrementing = false;
    protected $primaryKey = null;
}

