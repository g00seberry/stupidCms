<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для плагинов (Plugin).
 *
 * Представляет плагины системы с информацией о состоянии, метаданных и синхронизации.
 * Использует ULID в качестве первичного ключа.
 *
 * @property string $id ULID идентификатор
 * @property string $name Название плагина
 * @property string $slug Уникальный slug плагина
 * @property bool $enabled Флаг активности плагина
 * @property array|null $meta_json Метаданные плагина (версия, описание и т.д.)
 * @property \Illuminate\Support\Carbon|null $last_synced_at Дата последней синхронизации
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Plugin extends Model
{
    use HasUlids;
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
        'enabled' => 'boolean',
        'meta_json' => 'array',
        'last_synced_at' => 'immutable_datetime',
    ];

    /**
     * Тип первичного ключа (ULID строка).
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Отключить автоинкремент (используется ULID).
     *
     * @var bool
     */
    public $incrementing = false;
}

