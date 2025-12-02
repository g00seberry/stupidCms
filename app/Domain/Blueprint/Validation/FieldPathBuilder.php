<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\Rules\DistinctRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Построитель путей полей для валидации.
 *
 * Преобразует full_path из Path в путь для валидации с учётом cardinality.
 * Учитывает специфику правил валидации (например, distinct для массивов).
 *
 * @package App\Domain\Blueprint\Validation
 */
final class FieldPathBuilder
{
    /**
     * Построить путь поля в точечной нотации для валидации.
     *
     * Преобразует full_path из Path в путь для content_json.
     * Если родительский путь имеет cardinality: 'many', заменяет соответствующий сегмент на '*'.
     *
     * @param string $fullPath Полный путь из Path (например, 'author.contacts.phone')
     * @param array<string, string> $pathCardinalities Маппинг full_path → cardinality для всех путей
     * @param string $prefix Префикс для пути (по умолчанию 'content_json.')
     * @return string Путь в точечной нотации для валидации
     */
    public function buildFieldPath(
        string $fullPath,
        array $pathCardinalities,
        string $prefix = ValidationConstants::CONTENT_JSON_PREFIX
    ): string {
        $segments = explode('.', $fullPath);
        $resultSegments = [];
        $wildcardSegment = ltrim(ValidationConstants::ARRAY_ELEMENT_WILDCARD, '.');

        // Обрабатываем каждый сегмент пути
        for ($i = 0; $i < count($segments); $i++) {
            // Строим путь до текущего сегмента для проверки cardinality
            $parentPath = implode('.', array_slice($segments, 0, $i));

            // Если это не первый сегмент, проверяем cardinality родительского пути
            if ($i > 0 && isset($pathCardinalities[$parentPath]) && $pathCardinalities[$parentPath] === ValidationConstants::CARDINALITY_MANY) {
                // Родительский путь - массив, заменяем текущий сегмент на '*'
                $resultSegments[] = $wildcardSegment;
                // Добавляем имя сегмента после '*'
                $resultSegments[] = $segments[$i];
            } else {
                // Обычный сегмент пути
                $resultSegments[] = $segments[$i];
            }
        }

        return $prefix.implode('.', $resultSegments);
    }

    /**
     * Построить путь поля для конкретного правила валидации.
     *
     * Строит путь с учётом cardinality и специфики правила.
     * Для правил, которые должны применяться к элементам массива (например, distinct),
     * добавляет wildcard для элементов массива.
     *
     * @param string $fullPath Полный путь из Path (например, 'reading_time_minutes')
     * @param array<string, string> $pathCardinalities Маппинг full_path → cardinality для всех путей
     * @param \App\Domain\Blueprint\Validation\Rules\Rule $rule Правило валидации
     * @param string $currentPathCardinality Кардинальность текущего пути
     * @param string $prefix Префикс для пути (по умолчанию 'content_json.')
     * @return string Путь в точечной нотации для валидации
     */
    public function buildFieldPathForRule(
        string $fullPath,
        array $pathCardinalities,
        Rule $rule,
        string $currentPathCardinality,
        string $prefix = ValidationConstants::CONTENT_JSON_PREFIX
    ): string {
        $basePath = $this->buildFieldPath($fullPath, $pathCardinalities, $prefix);

        // Для правил, которые должны применяться к элементам массива (например, distinct),
        // добавляем wildcard для элементов массива, если текущий путь - массив
        if ($rule instanceof DistinctRule && $currentPathCardinality === ValidationConstants::CARDINALITY_MANY) {
            return $basePath . ValidationConstants::ARRAY_ELEMENT_WILDCARD;
        }

        return $basePath;
    }
}

