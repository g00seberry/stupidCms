<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaVariant extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'media_variants';

    public function media()
    {
        return $this->belongsTo(Media::class);
    }
}

