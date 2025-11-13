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

    /**
     * Прямой родитель (depth = 1)
     */
    public function parent()
    {
        return $this->belongsToMany(Term::class, 'term_tree', 'descendant_id', 'ancestor_id')
            ->wherePivot('depth', 1);
    }

    /**
     * Прямые потомки (depth = 1)
     */
    public function children()
    {
        return $this->belongsToMany(Term::class, 'term_tree', 'ancestor_id', 'descendant_id')
            ->wherePivot('depth', 1);
    }

    /**
     * Получить ID прямого родителя (accessor)
     */
    public function getParentIdAttribute(): ?int
    {
        if ($this->relationLoaded('parent')) {
            $parent = $this->getRelation('parent');
            // parent() возвращает BelongsToMany, который при загрузке даёт коллекцию
            if ($parent instanceof \Illuminate\Database\Eloquent\Collection) {
                return $parent->first()?->id;
            }
            return $parent?->id;
        }

        // Если связь не загружена, делаем запрос
        $parent = $this->ancestors()
            ->wherePivot('depth', 1)
            ->first();
        
        return $parent?->id;
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

