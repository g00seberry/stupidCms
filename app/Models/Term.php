<?php

namespace App\Models;

use Database\Factories\TermFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Term extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function taxonomy()
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function entries()
    {
        return $this->belongsToMany(Entry::class, 'entry_term', 'term_id', 'entry_id')
            ->withTimestamps();
    }

    // Closure-table: предки/потомки
    public function ancestors()
    {
        return $this->belongsToMany(Term::class, 'term_tree', 'descendant_id', 'ancestor_id')
            ->withPivot('depth');
    }

    public function descendants()
    {
        return $this->belongsToMany(Term::class, 'term_tree', 'ancestor_id', 'descendant_id')
            ->withPivot('depth');
    }

    public function scopeInTaxonomy(Builder $q, string $taxonomySlug): Builder
    {
        return $q->whereHas('taxonomy', fn ($qq) => $qq->where('slug', $taxonomySlug));
    }

    protected static function newFactory(): TermFactory
    {
        return TermFactory::new();
    }
}

