<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AsPostTypeOptions;
use App\Domain\PostTypes\PostTypeOptions;
use Database\Factories\PostTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent модель для типов записей (PostType).
 *
 * Определяет типы контента в CMS (например, 'article', 'page', 'post').
 * Каждый тип может иметь свои опции и настройки.
 *
 * @property int $id
 * @property string $slug Уникальный slug типа записи
 * @property string $name Название типа записи
 * @property \App\Domain\PostTypes\PostTypeOptions $options_json Опции типа записи
 * @property int|null $blueprint_id Blueprint, определяющий структуру Entry
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Entry> $entries Записи этого типа
 * @property-read \App\Models\Blueprint|null $blueprint Blueprint, определяющий структуру Entry
 */
class PostType extends Model
{
    use HasFactory;

    /**
     * Mass-assignable fields.
     *
     * Note: While all fields are fillable for factory/seeder convenience,
     * the Admin API only updates options_json (enforced by controller logic).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'name',
        'options_json',
        'blueprint_id',
    ];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options_json' => AsPostTypeOptions::class,
    ];

    /**
     * Связь с записями этого типа.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Entry, \App\Models\PostType>
     */
    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * Blueprint, определяющий структуру Entry этого типа.
     *
     * @return BelongsTo<Blueprint, PostType>
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\PostTypeFactory
     */
    protected static function newFactory(): PostTypeFactory
    {
        return PostTypeFactory::new();
    }
}

