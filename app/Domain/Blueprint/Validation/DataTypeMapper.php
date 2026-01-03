<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\ValidationConstants;

/**
 * Маппер типов данных Path в типы для валидации.
 *
 * Преобразует data_type из Path (string, text, int, float, bool, datetime, json, ref, media)
 * в типы для валидации (string, integer, numeric, boolean, date, array).
 *
 * @package App\Domain\Blueprint\Validation
 */
final class DataTypeMapper
{
    /**
     * Маппинг data_type → тип для валидации.
     *
     * @var array<string, string>
     */
    private const DATA_TYPE_MAPPING = [
        'string' => 'string',
        'text' => 'string',
        'int' => 'integer',
        'float' => 'numeric',
        'bool' => 'boolean',
        'datetime' => 'date',
        'json' => 'array',
        'ref' => 'integer',
        'media' => 'string',
    ];

    /**
     * Преобразовать data_type в тип для валидации.
     *
     * Для cardinality = 'many' возвращает тип элемента массива (не 'array', т.к. правило array добавляется отдельно).
     * Для cardinality = 'one' возвращает тип самого поля.
     *
     * @param string $dataType data_type из Path (string, text, int, float, bool, datetime, json, ref, media)
     * @param string $cardinality Кардинальность поля ('one' или 'many')
     * @return string|null Тип для валидации или null, если тип неизвестен
     */
    public function mapToValidationType(string $dataType, string $cardinality): ?string
    {
        // Для cardinality = 'many' возвращаем тип элемента массива (правило array добавляется отдельно)
        // Для cardinality = 'one' возвращаем тип самого поля
        return self::DATA_TYPE_MAPPING[$dataType] ?? null;
    }

    /**
     * Проверить, поддерживается ли data_type.
     *
     * @param string $dataType data_type из Path
     * @return bool true, если тип поддерживается
     */
    public function isSupported(string $dataType): bool
    {
        return isset(self::DATA_TYPE_MAPPING[$dataType]);
    }
}

