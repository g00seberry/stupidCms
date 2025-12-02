<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;

/**
 * Правило валидации: уникальность элементов массива с сравнением по JSON-сериализации.
 *
 * Проверяет, что все элементы массива уникальны, сравнивая их JSON-сериализацию.
 * Работает для массивов простых значений и массивов объектов.
 * Для объектов сравнение происходит по их JSON-представлению (как строки).
 *
 * @package App\Rules
 */
final class DistinctObjects implements ValidationRule
{
    /**
     * Выполнить правило валидации.
     *
     * Сравнивает элементы массива по их JSON-сериализации.
     * Если найдены дубликаты, добавляет ошибку валидации.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Если значение не массив, пропускаем (должно быть обработано правилом 'array')
        if (! is_array($value)) {
            return;
        }

        // Сериализуем каждый элемент в JSON для сравнения
        $serialized = [];
        $duplicates = [];

        foreach ($value as $index => $item) {
            try {
                // Сортируем ключи для консистентного сравнения объектов
                $json = $this->normalizeAndSerialize($item);
                $hash = md5($json);

                if (isset($serialized[$hash])) {
                    // Найден дубликат
                    $duplicates[] = $index;
                } else {
                    $serialized[$hash] = $index;
                }
            } catch (JsonException $e) {
                // Если элемент не может быть сериализован, пропускаем
                // (это должно обрабатываться другими правилами валидации)
                continue;
            }
        }

        if (! empty($duplicates)) {
            $fail('The :attribute field has duplicate values.');
        }
    }

    /**
     * Нормализовать и сериализовать значение в JSON.
     *
     * Сортирует ключи объектов для консистентного сравнения.
     *
     * @param mixed $value Значение для сериализации
     * @return string JSON-строка
     * @throws \JsonException
     */
    private function normalizeAndSerialize(mixed $value): string
    {
        // Нормализуем значение (сортируем ключи объектов)
        $normalized = $this->normalizeValue($value);

        return json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Нормализовать значение, сортируя ключи объектов.
     *
     * @param mixed $value Значение для нормализации
     * @return mixed Нормализованное значение
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            // Проверяем, является ли массив ассоциативным (объектом)
            if ($this->isAssociative($value)) {
                // Сортируем ключи
                ksort($value);
                // Рекурсивно нормализуем значения
                foreach ($value as $key => $item) {
                    $value[$key] = $this->normalizeValue($item);
                }
            } else {
                // Для индексированных массивов нормализуем каждый элемент
                foreach ($value as $key => $item) {
                    $value[$key] = $this->normalizeValue($item);
                }
            }
        }

        return $value;
    }

    /**
     * Проверить, является ли массив ассоциативным.
     *
     * @param array<mixed> $array Массив для проверки
     * @return bool true, если массив ассоциативный
     */
    private function isAssociative(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}

