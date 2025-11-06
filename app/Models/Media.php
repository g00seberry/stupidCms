<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['meta_json' => 'array'];

    public function variants()
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function entries()
    {
        return $this->belongsToMany(Entry::class, 'entry_media', 'media_id', 'entry_id')
            ->using(EntryMedia::class)
            ->withPivot('field_key');
    }
}

