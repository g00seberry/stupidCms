<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaxonomyFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для таксономий (Taxonomy).
 *
 * Определяет группы термов: категории, теги, метки и т.д.
 * Может быть иерархической (hierarchical = true) или плоской (hierarchical = false).
 *
 * @property int $id
 * @property string $name Название таксономии
 * @property array|null $options_json Дополнительные опции таксономии (JSON)
 * @property bool $hierarchical Флаг иерархической структуры (true = поддерживает родитель-потомок)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read string|null $label Алиас для name (accessor)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Term> $terms Термы этой таксономии
 */
class Taxonomy extends Model
{
    use HasFactory;

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
        'options_json' => 'array',
        'hierarchical' => 'boolean',
    ];

    /**
     * Связь с термами этой таксономии.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Term, \App\Models\Taxonomy>
     */
    public function terms()
    {
        return $this->hasMany(Term::class);
    }

    /**
     * Accessor для label (алиас для name).
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute<string|null, string>
     */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['name'] ?? null,
            set: fn ($value) => ['name' => $value],
        );
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\TaxonomyFactory
     */
    protected static function newFactory(): TaxonomyFactory
    {
        return TaxonomyFactory::new();
    }
}

