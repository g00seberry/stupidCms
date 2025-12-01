<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

/**
 * Маппер типов данных Path в правила валидации Laravel.
 *
 * @package App\Domain\Blueprint\Validation
 */
final class DataTypeMapper
{
    /**
     * Преобразовать data_type Path в Laravel правило валидации.
     *
     * @param string $dataType Тип данных Path
     * @return string|null Правило валидации Laravel или null
     */
    public function toLaravelRule(string $dataType): ?string
    {
        return match ($dataType) {
            ValidationConstants::DATA_TYPE_STRING,
            ValidationConstants::DATA_TYPE_TEXT => 'string',
            ValidationConstants::DATA_TYPE_INT => 'integer',
            ValidationConstants::DATA_TYPE_FLOAT => 'numeric',
            ValidationConstants::DATA_TYPE_BOOL => 'boolean',
            ValidationConstants::DATA_TYPE_DATETIME => 'date',
            ValidationConstants::DATA_TYPE_JSON => ValidationConstants::RULE_ARRAY,
            ValidationConstants::DATA_TYPE_REF => 'integer',
            ValidationConstants::DATA_TYPE_ARRAY => ValidationConstants::RULE_ARRAY,
            default => null,
        };
    }

    /**
     * Проверить, является ли тип данных массивом.
     *
     * @param string $dataType Тип данных
     * @return bool
     */
    public function isArrayType(string $dataType): bool
    {
        return in_array($dataType, [
            ValidationConstants::DATA_TYPE_JSON,
            ValidationConstants::DATA_TYPE_ARRAY,
        ], true);
    }

    /**
     * Проверить, является ли тип данных JSON.
     *
     * @param string $dataType Тип данных
     * @return bool
     */
    public function isJsonType(string $dataType): bool
    {
        return $dataType === ValidationConstants::DATA_TYPE_JSON;
    }
}

