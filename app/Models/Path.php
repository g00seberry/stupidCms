<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Поле внутри blueprint с материализованным full_path.
 *
 * @property int $id
 * @property int $blueprint_id Владелец поля
 * @property int|null $source_blueprint_id Откуда скопировано (если копия)
 * @property int|null $blueprint_embed_id К какому embed привязано (если копия)
 * @property int|null $parent_id Родительский path
 * @property string $name Локальное имя поля
 * @property string $full_path Материализованный путь (e.g., 'author.contacts.phone')
 * @property string $data_type string|text|int|float|bool|datetime|json|ref
 * @property string $cardinality one|many
 * @property bool $is_indexed
 * @property bool $is_readonly Нельзя редактировать (копия)
 * @property int $sort_order
 * @property array|null $validation_rules JSON-правила валидации
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Blueprint $blueprint
 * @property-read \App\Models\Blueprint|null $sourceBlueprint
 * @property-read \App\Models\BlueprintEmbed|null $blueprintEmbed
 * @property-read \App\Models\Path|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $children
 */
class Path extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'blueprint_id',
        'parent_id',
        'name',
        'data_type',
        'cardinality',
        'is_indexed',
        'sort_order',
        'validation_rules',
    ];

    /**
     * ЗАЩИТА ПОЛЕЙ: нельзя массово заполнить (только через сервис).
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'source_blueprint_id',
        'blueprint_embed_id',
        'is_readonly',
        'full_path',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_indexed' => 'boolean',
        'is_readonly' => 'boolean',
        'validation_rules' => 'array',
    ];

    /**
     * Владелец поля.
     *
     * @return BelongsTo<Blueprint, Path>
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Откуда скопировано (если копия).
     *
     * @return BelongsTo<Blueprint, Path>
     */
    public function sourceBlueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class, 'source_blueprint_id');
    }

    /**
     * К какому embed привязано (если копия).
     *
     * @return BelongsTo<BlueprintEmbed, Path>
     */
    public function blueprintEmbed(): BelongsTo
    {
        return $this->belongsTo(BlueprintEmbed::class);
    }

    /**
     * Родительский path (для вложенных полей).
     *
     * @return BelongsTo<Path, Path>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Path::class, 'parent_id');
    }

    /**
     * Дочерние paths.
     *
     * @return HasMany<Path>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Path::class, 'parent_id');
    }

    /**
     * Собственное поле (не копия)?
     *
     * @return bool
     */
    public function isOwn(): bool
    {
        return $this->source_blueprint_id === null;
    }

    /**
     * Копия из другого blueprint?
     *
     * @return bool
     */
    public function isCopied(): bool
    {
        return $this->source_blueprint_id !== null;
    }
}
