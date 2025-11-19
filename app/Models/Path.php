<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Database\Factories\PathFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Модель Path — метаданные поля в Blueprint.
 *
 * @property int $id
 * @property int $blueprint_id
 * @property int|null $source_component_id
 * @property int|null $source_path_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $full_path
 * @property string $data_type
 * @property string $cardinality
 * @property bool $is_indexed
 * @property bool $is_required
 * @property string|null $ref_target_type
 * @property int|null $embedded_blueprint_id
 * @property int|null $embedded_root_path_id
 * @property array|null $validation_rules
 * @property array|null $ui_options
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\Blueprint $blueprint
 * @property-read \App\Models\Blueprint|null $embeddedBlueprint
 * @property-read \App\Models\Path|null $parent
 * @property-read \App\Models\Path|null $embeddedRootPath
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DocValue> $values
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DocRef> $refs
 */
class Path extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'blueprint_id',
        'source_component_id',
        'source_path_id',
        'parent_id',
        'name',
        'full_path',
        'data_type',
        'cardinality',
        'is_indexed',
        'is_required',
        'ref_target_type',
        'embedded_blueprint_id',
        'embedded_root_path_id',
        'validation_rules',
        'ui_options',
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'ui_options' => 'array',
        'is_indexed' => 'boolean',
        'is_required' => 'boolean',
    ];

    // Связи

    /**
     * Связь с Blueprint.
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Связь с встроенным Blueprint (для data_type='blueprint').
     */
    public function embeddedBlueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class, 'embedded_blueprint_id');
    }

    /**
     * Корневой Path, от которого материализованы вложенные поля.
     */
    public function embeddedRootPath(): BelongsTo
    {
        return $this->belongsTo(Path::class, 'embedded_root_path_id');
    }

    /**
     * Родительский Path (для вложенных полей).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Path::class, 'parent_id');
    }

    /**
     * Дочерние Paths.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Path::class, 'parent_id');
    }

    /**
     * Связь с DocValue.
     */
    public function values(): HasMany
    {
        return $this->hasMany(DocValue::class);
    }

    /**
     * Связь с DocRef.
     */
    public function refs(): HasMany
    {
        return $this->hasMany(DocRef::class);
    }

    // Методы

    /**
     * Является ли это поле ссылкой.
     */
    public function isRef(): bool
    {
        return $this->data_type === 'ref';
    }

    /**
     * Является ли это поле встроенным Blueprint.
     */
    public function isEmbeddedBlueprint(): bool
    {
        return $this->data_type === 'blueprint';
    }

    /**
     * Является ли это поле массивом.
     */
    public function isMany(): bool
    {
        return $this->cardinality === 'many';
    }

    /**
     * Фабрика модели.
     */
    protected static function newFactory(): PathFactory
    {
        return PathFactory::new();
    }
}

