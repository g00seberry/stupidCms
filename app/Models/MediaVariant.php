<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class MediaVariant extends Model
{
    use HasFactory;
    use HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];
    protected $table = 'media_variants';

    public function media()
    {
        return $this->belongsTo(Media::class);
    }
}

