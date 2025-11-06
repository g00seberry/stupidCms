<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Term extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['meta_json' => 'array'];

    public function taxonomy()
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function entries()
    {
        return $this->belongsToMany(Entry::class, 'entry_term', 'term_id', 'entry_id');
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
        return $q->whereHas('taxonomy', fn($qq) => $qq->where('slug', $taxonomySlug));
    }
}

