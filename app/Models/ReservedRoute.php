<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Routing\PathNormalizer;
use App\Domain\Routing\Exceptions\InvalidPathException;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для зарезервированных путей (ReservedRoute).
 *
 * Хранит пути, которые зарезервированы системой и не могут использоваться
 * для записей контента. Поддерживает два типа: 'path' (точное совпадение)
 * и 'prefix' (префикс пути).
 *
 * @property int $id
 * @property string $path Нормализованный путь (автоматически нормализуется при установке)
 * @property string $kind Тип резервации: 'path' (точное совпадение) или 'prefix' (префикс)
 * @property string|null $source Источник резервации (например, 'system', 'plugin.name')
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ReservedRoute extends Model
{
    /**
     * Mass-assignable fields.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'path',
        'kind',
        'source',
    ];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Мутатор для автоматической нормализации пути при установке.
     *
     * Защищает от прямого создания модели без нормализации.
     * Автоматически нормализует путь через PathNormalizer.
     *
     * @param string $value Исходный путь
     * @return void
     * @throws \App\Domain\Routing\Exceptions\InvalidPathException Если путь невалиден
     */
    public function setPathAttribute(string $value): void
    {
        $this->attributes['path'] = PathNormalizer::normalize($value);
    }
}

