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
 * @property int|null $blueprint_embed_id К какому embed привязано (если копия)
 * @property int|null $parent_id Родительский path
 * @property string $name Локальное имя поля
 * @property string $full_path Материализованный путь (e.g., 'author.contacts.phone')
 * @property string $data_type string|text|int|float|bool|datetime|json|ref|media
 * @property string $cardinality one|many
 * @property bool $is_indexed
 * @property array|null $validation_rules JSON-правила валидации
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Blueprint $blueprint
 * @property-read \App\Models\BlueprintEmbed|null $blueprintEmbed
 * @property-read \App\Models\Path|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PathRefConstraint> $refConstraints Ограничения на допустимые PostType для ref-полей
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PathMediaConstraint> $mediaConstraints Ограничения на допустимые MIME-типы для media-полей
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
        'validation_rules',
    ];

    /**
     * ЗАЩИТА ПОЛЕЙ: нельзя массово заполнить (только через сервис).
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'blueprint_embed_id',
        'full_path',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_indexed' => 'boolean',
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
     * Ограничения на допустимые PostType для ref-полей.
     *
     * @return HasMany<PathRefConstraint>
     */
    public function refConstraints(): HasMany
    {
        return $this->hasMany(PathRefConstraint::class);
    }

    /**
     * Ограничения на допустимые MIME-типы для media-полей.
     *
     * @return HasMany<PathMediaConstraint>
     */
    public function mediaConstraints(): HasMany
    {
        return $this->hasMany(PathMediaConstraint::class);
    }

    /**
     * Получить список ID допустимых PostType для этого ref-поля.
     *
     * @return array<int> Массив ID допустимых PostType
     */
    public function getAllowedPostTypeIds(): array
    {
        return $this->refConstraints()
            ->pluck('allowed_post_type_id')
            ->toArray();
    }

    /**
     * Проверить, есть ли у этого path ограничения на ref-поля.
     *
     * @return bool true, если есть хотя бы одно ограничение
     */
    public function hasRefConstraints(): bool
    {
        return $this->refConstraints()->exists();
    }

    /**
     * Получить список допустимых MIME-типов для этого media-поля.
     *
     * @return array<string> Массив допустимых MIME-типов
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->mediaConstraints()
            ->pluck('allowed_mime')
            ->toArray();
    }

    /**
     * Проверить, есть ли у этого path ограничения на media-поля.
     *
     * @return bool true, если есть хотя бы одно ограничение
     */
    public function hasMediaConstraints(): bool
    {
        return $this->mediaConstraints()->exists();
    }

    /**
     * Собственное поле (не копия)?
     *
     * @return bool
     */
    public function isOwn(): bool
    {
        return $this->blueprint_embed_id === null;
    }

    /**
     * Копия из другого blueprint?
     *
     * @return bool
     */
    public function isCopied(): bool
    {
        return $this->blueprint_embed_id !== null;
    }
}
