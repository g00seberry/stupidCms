<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

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
     * Преобразует full_path из Path в путь для data_json.
     * Если родительский путь имеет cardinality: 'many', заменяет соответствующий сегмент на '*'.
     *
     * @param string $fullPath Полный путь из Path (например, 'author.contacts.phone')
     * @param array<string, string> $pathCardinalities Маппинг full_path → cardinality для всех путей
     * @param string $prefix Префикс для пути (по умолчанию 'data_json.')
     * @return string Путь в точечной нотации для валидации
     */
    public function buildFieldPath(
        string $fullPath,
        array $pathCardinalities,
        string $prefix = ValidationConstants::data_json_PREFIX
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
}

