<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntrySlug extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'entry_slugs';
    public $incrementing = false;
    protected $primaryKey = null;
    protected $casts = ['is_current' => 'boolean', 'created_at' => 'datetime'];

    public function entry()
    {
        return $this->belongsTo(Entry::class);
    }
}

