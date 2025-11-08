<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Media extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'exif_json' => 'array',
        'deleted_at' => 'datetime',
    ];

    public function variants()
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function entries()
    {
        return $this->belongsToMany(Entry::class, 'entry_media', 'media_id', 'entry_id')
            ->using(EntryMedia::class)
            ->withPivot(['field_key', 'order']);
    }

    public function kind(): string
    {
        return match (true) {
            str_starts_with($this->mime, 'image/') => 'image',
            str_starts_with($this->mime, 'video/') => 'video',
            str_starts_with($this->mime, 'audio/') => 'audio',
            default => 'document',
        };
    }
}

