<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PostTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для типов записей (PostType).
 *
 * Определяет типы контента в CMS (например, 'article', 'page', 'post').
 * Каждый тип может иметь свои опции и настройки.
 *
 * @property int $id
 * @property string $slug Уникальный slug типа записи
 * @property string $name Название типа записи
 * @property array $options_json Дополнительные опции типа (JSON)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Entry> $entries Записи этого типа
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
    ];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options_json' => 'array',
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
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\PostTypeFactory
     */
    protected static function newFactory(): PostTypeFactory
    {
        return PostTypeFactory::new();
    }
}

