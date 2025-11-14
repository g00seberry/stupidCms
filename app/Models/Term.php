<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TermFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent модель для термов (Term).
 *
 * Представляет элементы таксономии: категории, теги, метки и т.д.
 * Поддерживает иерархическую структуру через closure-table (term_tree).
 * Поддерживает мягкое удаление.
 *
 * @property int $id
 * @property int $taxonomy_id ID таксономии
 * @property string $name Название терма
 * @property array|null $meta_json Дополнительные метаданные терма
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at Дата мягкого удаления
 *
 * @property-read \App\Models\Taxonomy $taxonomy Таксономия, к которой относится терм
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Entry> $entries Записи, связанные с этим термом
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Term> $ancestors Все предки терма (через closure-table)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Term> $descendants Все потомки терма (через closure-table)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Term> $parent Прямой родитель (depth = 1)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Term> $children Прямые потомки (depth = 1)
 * @property-read int|null $parent_id ID прямого родителя (accessor)
 */
class Term extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Все поля доступны для массового присвоения.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta_json' => 'array',
    ];

    /**
     * Связь с таксономией.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Taxonomy, \App\Models\Term>
     */
    public function taxonomy()
    {
        return $this->belongsTo(Taxonomy::class);
    }

    /**
     * Связь с записями, связанными с этим термом.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Entry, \App\Models\Term>
     */
    public function entries()
    {
        return $this->belongsToMany(Entry::class, 'entry_term', 'term_id', 'entry_id')
            ->withTimestamps();
    }

    /**
     * Связь: все предки терма через closure-table.
     *
     * Возвращает всех предков терма с информацией о глубине (depth) в иерархии.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Term, \App\Models\Term>
     */
    public function ancestors()
    {
        return $this->belongsToMany(Term::class, 'term_tree', 'descendant_id', 'ancestor_id')
            ->withPivot('depth');
    }

    /**
     * Связь: все потомки терма через closure-table.
     *
     * Возвращает всех потомков терма с информацией о глубине (depth) в иерархии.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Term, \App\Models\Term>
     */
    public function descendants()
    {
        return $this->belongsToMany(Term::class, 'term_tree', 'ancestor_id', 'descendant_id')
            ->withPivot('depth');
    }

    /**
     * Связь: прямой родитель (depth = 1).
     *
     * Возвращает только непосредственного родителя терма в иерархии.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Term, \App\Models\Term>
     */
    public function parent()
    {
        return $this->belongsToMany(Term::class, 'term_tree', 'descendant_id', 'ancestor_id')
            ->wherePivot('depth', 1);
    }

    /**
     * Связь: прямые потомки (depth = 1).
     *
     * Возвращает только непосредственных потомков терма в иерархии.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Term, \App\Models\Term>
     */
    public function children()
    {
        return $this->belongsToMany(Term::class, 'term_tree', 'ancestor_id', 'descendant_id')
            ->wherePivot('depth', 1);
    }

    /**
     * Получить ID прямого родителя (accessor).
     *
     * Если связь parent загружена, использует её. Иначе выполняет запрос к БД.
     *
     * @return int|null ID прямого родителя или null, если родителя нет
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

    /**
     * Скоуп: термы определённой таксономии.
     *
     * Фильтрует термы по ID таксономии.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Term> $q
     * @param int $taxonomyId ID таксономии
     * @return \Illuminate\Database\Eloquent\Builder<Term>
     */
    public function scopeInTaxonomy(Builder $q, int $taxonomyId): Builder
    {
        return $q->where('taxonomy_id', $taxonomyId);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\TermFactory
     */
    protected static function newFactory(): TermFactory
    {
        return TermFactory::new();
    }
}

