<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;

/**
 * Eloquent cast для JSON значений.
 *
 * Преобразует JSON строки в массивы при чтении и массивы в JSON строки при записи.
 * Обрабатывает ошибки декодирования (возвращает null) и кодирования (выбрасывает исключение).
 *
 * @package App\Casts
 */
final class AsJsonValue implements CastsAttributes
{
    /**
     * Преобразовать атрибут из значения модели в значение для приложения.
     *
     * Декодирует JSON строку в массив. При ошибке декодирования возвращает null.
     *
     * @param \Illuminate\Database\Eloquent\Model $model Модель
     * @param string $key Имя атрибута
     * @param mixed $value Значение из БД (JSON строка или null)
     * @param array<string, mixed> $attributes Все атрибуты модели
     * @return mixed Декодированное значение или null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            /** @var mixed */
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Преобразовать атрибут из значения приложения в значение для БД.
     *
     * Кодирует значение в JSON строку. При ошибке кодирования выбрасывает InvalidArgumentException.
     *
     * @param \Illuminate\Database\Eloquent\Model $model Модель
     * @param string $key Имя атрибута
     * @param mixed $value Значение для кодирования
     * @param array<string, mixed> $attributes Все атрибуты модели
     * @return string JSON строка
     * @throws \InvalidArgumentException Если значение не может быть закодировано в JSON
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                sprintf('Unable to encode %s attribute to JSON: %s', $key, $exception->getMessage()),
                previous: $exception
            );
        }
    }
}

