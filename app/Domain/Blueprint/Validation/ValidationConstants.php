<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

/**
 * Константы для системы валидации Blueprint.
 *
 * @package App\Domain\Blueprint\Validation
 */
final class ValidationConstants
{
    /**
     * Правила валидации Laravel.
     */
    public const RULE_REQUIRED = 'required';
    public const RULE_NULLABLE = 'nullable';
    public const RULE_ARRAY = 'array';

    /**
     * Кардинальность полей.
     */
    public const CARDINALITY_ONE = 'one';
    public const CARDINALITY_MANY = 'many';

    /**
     * Префикс для полей content_json.
     */
    public const CONTENT_JSON_PREFIX = 'content_json.';

    /**
     * Wildcard для элементов массивов.
     */
    public const ARRAY_ELEMENT_WILDCARD = '.*';

    /**
     * Типы данных Path.
     */
    public const DATA_TYPE_STRING = 'string';
    public const DATA_TYPE_TEXT = 'text';
    public const DATA_TYPE_INT = 'int';
    public const DATA_TYPE_FLOAT = 'float';
    public const DATA_TYPE_BOOL = 'bool';
    public const DATA_TYPE_DATETIME = 'datetime';
    public const DATA_TYPE_JSON = 'json';
    public const DATA_TYPE_REF = 'ref';
    public const DATA_TYPE_ARRAY = 'array';

    /**
     * Получить массив правил required/nullable для проверки.
     *
     * @return array<string>
     */
    public static function getRequiredNullableRules(): array
    {
        return [self::RULE_REQUIRED, self::RULE_NULLABLE];
    }
}

