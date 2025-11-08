<?php

namespace App\Models;

use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Entry extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'data_json' => 'array',
        'seo_json' => 'array',
        'published_at' => 'datetime',
    ];

    // Связи
    public function postType()
    {
        return $this->belongsTo(PostType::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function slugs()
    {
        return $this->hasMany(EntrySlug::class);
    }

    public function terms()
    {
        return $this->belongsToMany(Term::class, 'entry_term', 'entry_id', 'term_id');
    }

    public function media()
    {
        return $this->belongsToMany(Media::class, 'entry_media', 'entry_id', 'media_id')
            ->using(EntryMedia::class)
            ->withPivot('field_key');
    }

    // Скоупы
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', Carbon::now('UTC'));
    }

    public function scopeOfType(Builder $q, string $postTypeSlug): Builder
    {
        return $q->whereHas('postType', fn($qq) => $qq->where('slug', $postTypeSlug));
    }

    // Хелпер: публичный URL (для Page — плоский URL)
    public function url(): string
    {
        $slug = $this->slug;
        $type = $this->relationLoaded('postType') ? $this->postType->slug : $this->postType()->value('slug');
        return $type === 'page' ? "/{$slug}" : sprintf('/%s/%s', $type, $slug);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): EntryFactory
    {
        return EntryFactory::new();
    }
}

