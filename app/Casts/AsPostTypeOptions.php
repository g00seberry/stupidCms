<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\PostTypes\PostTypeOptions;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;

/**
 * Eloquent cast для PostTypeOptions.
 *
 * Преобразует JSON строки в PostTypeOptions при чтении
 * и PostTypeOptions в JSON строки при записи.
 *
 * @package App\Casts
 */
final class AsPostTypeOptions implements CastsAttributes
{
    /**
     * Преобразовать атрибут из значения модели в значение для приложения.
     *
     * Декодирует JSON строку в PostTypeOptions. При ошибке декодирования
     * или null возвращает пустые опции.
     *
     * @param \Illuminate\Database\Eloquent\Model $model Модель
     * @param string $key Имя атрибута
     * @param mixed $value Значение из БД (JSON строка или null)
     * @param array<string, mixed> $attributes Все атрибуты модели
     * @return \App\Domain\PostTypes\PostTypeOptions Декодированные опции
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): PostTypeOptions
    {
        if ($value === null) {
            return PostTypeOptions::empty();
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                return PostTypeOptions::empty();
            }

            return PostTypeOptions::fromArray($decoded);
        } catch (JsonException) {
            return PostTypeOptions::empty();
        }
    }

    /**
     * Преобразовать атрибут из значения приложения в значение для БД.
     *
     * Кодирует PostTypeOptions в JSON строку. При ошибке кодирования
     * выбрасывает исключение.
     *
     * @param \Illuminate\Database\Eloquent\Model $model Модель
     * @param string $key Имя атрибута
     * @param mixed $value Значение для сохранения (PostTypeOptions или array)
     * @param array<string, mixed> $attributes Все атрибуты модели
     * @return string|null JSON строка или null
     * @throws \InvalidArgumentException Если значение не может быть преобразовано
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // Если уже PostTypeOptions, используем его
        if ($value instanceof PostTypeOptions) {
            $options = $value;
        } elseif (is_array($value)) {
            // Если массив, создаём PostTypeOptions из него
            $options = PostTypeOptions::fromArray($value);
        } else {
            throw new InvalidArgumentException(
                sprintf('Value for %s must be PostTypeOptions or array, got %s', $key, get_debug_type($value))
            );
        }

        try {
            $json = json_encode($options->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            return $json === '[]' ? null : $json;
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                sprintf('Failed to encode PostTypeOptions to JSON: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}

