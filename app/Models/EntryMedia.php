<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EntryMedia extends Pivot
{
    public $timestamps = false;
    protected $table = 'entry_media';
    protected $guarded = [];
}

