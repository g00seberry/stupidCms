<?php

declare(strict_types=1);

namespace App\Domain\PostTypes;

use InvalidArgumentException;

/**
 * Value Object для опций типа записи (PostType).
 *
 * Представляет типобезопасную структуру options_json для PostType.
 * Гарантирует валидность данных и централизованную нормализацию.
 *
 * Схема:
 * - taxonomies: array<string> - массив slug таксономий, разрешённых для этого типа записи
 * - fields: array<string, mixed> - произвольные поля (расширяемые)
 *
 * @package App\Domain\PostTypes
 */
final class PostTypeOptions
{
    /**
     * @param array<string> $taxonomies Массив slug таксономий
     * @param array<string, mixed> $fields Произвольные поля
     */
    private function __construct(
        public readonly array $taxonomies,
        public readonly array $fields,
    ) {
    }

    /**
     * Создать из массива (нормализует данные).
     *
     * @param array<string, mixed> $data Исходные данные
     * @return self Экземпляр PostTypeOptions
     */
    public static function fromArray(array $data): self
    {
        // Нормализуем taxonomies - всегда массив
        $taxonomies = [];
        if (isset($data['taxonomies'])) {
            $taxonomiesValue = $data['taxonomies'];
            if (is_array($taxonomiesValue) && array_is_list($taxonomiesValue)) {
                $taxonomies = array_values(array_filter($taxonomiesValue, fn ($item) => is_string($item)));
            }
        }

        // Извлекаем fields (все остальные поля, кроме taxonomies)
        $fields = $data;
        unset($fields['taxonomies']);

        return new self($taxonomies, $fields);
    }

    /**
     * Создать пустые опции.
     *
     * @return self Экземпляр PostTypeOptions с пустыми значениями
     */
    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * Преобразовать в массив для сохранения в БД.
     *
     * @return array<string, mixed> Массив для сериализации в JSON
     */
    public function toArray(): array
    {
        $result = $this->fields;

        // taxonomies всегда присутствует (даже если пустой массив)
        $result['taxonomies'] = $this->taxonomies;

        return $result;
    }

    /**
     * Преобразовать в массив/объект для API ответа (с нормализацией JSON объектов).
     *
     * Преобразует пустые массивы в объекты (кроме taxonomies).
     *
     * @return array<string, mixed>|\stdClass Массив или объект для API ответа
     */
    public function toApiArray(): array|\stdClass
    {
        $result = $this->toArray();

        // Преобразуем пустые массивы в объекты (кроме taxonomies)
        return self::normalizeForApi($result);
    }

    /**
     * Получить список разрешённых таксономий.
     *
     * @return array<string> Массив slug таксономий
     */
    public function getAllowedTaxonomies(): array
    {
        return $this->taxonomies;
    }

    /**
     * Проверить, разрешена ли таксономия для этого типа записи.
     *
     * @param string $taxonomySlug Slug таксономии
     * @return bool true, если таксономия разрешена
     */
    public function isTaxonomyAllowed(string $taxonomySlug): bool
    {
        if (empty($this->taxonomies)) {
            return true; // Если список пуст, все таксономии разрешены
        }

        return in_array($taxonomySlug, $this->taxonomies, true);
    }

    /**
     * Получить значение поля.
     *
     * @param string $key Ключ поля
     * @param mixed $default Значение по умолчанию
     * @return mixed Значение поля или default
     */
    public function getField(string $key, mixed $default = null): mixed
    {
        return $this->fields[$key] ?? $default;
    }

    /**
     * Проверить наличие поля.
     *
     * @param string $key Ключ поля
     * @return bool true, если поле существует
     */
    public function hasField(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    /**
     * Нормализовать массив для API ответа (преобразует пустые массивы в объекты, кроме taxonomies).
     *
     * @param array<string, mixed> $data Данные для нормализации
     * @return array<string, mixed>|object Нормализованные данные
     */
    private static function normalizeForApi(mixed $data): mixed
    {
        if ($data === null) {
            return new \stdClass();
        }

        if (! is_array($data)) {
            return $data;
        }

        if ($data === []) {
            return new \stdClass();
        }

        if (array_is_list($data)) {
            return array_map(fn ($item) => self::normalizeForApi($item), $data);
        }

        $object = new \stdClass();
        foreach ($data as $key => $value) {
            // taxonomies всегда остается массивом
            if ($key === 'taxonomies') {
                $object->{$key} = $value;
            } else {
                $object->{$key} = self::normalizeForApi($value);
            }
        }

        return $object;
    }
}

