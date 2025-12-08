<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AsJsonValue;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent модель для опций системы (Option).
 *
 * Хранит настройки системы в формате ключ-значение с поддержкой пространств имён.
 * Использует ULID в качестве первичного ключа. Поддерживает мягкое удаление.
 *
 * @property string $id ULID идентификатор
 * @property string $namespace Пространство имён опции (например, 'app', 'custom')
 * @property string $key Ключ опции (уникален в рамках namespace)
 * @property mixed $value_json Значение опции (любой JSON-совместимый тип)
 * @property string|null $description Описание опции
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at Дата мягкого удаления
 */
class Option extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    /**
     * Имя таблицы.
     *
     * @var string
     */
    protected $table = 'options';

    /**
     * Mass-assignable fields.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'namespace',
        'key',
        'value_json',
        'description',
    ];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value_json' => AsJsonValue::class,
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
