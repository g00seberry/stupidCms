<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Интерфейс конвертера правил валидации из Path в доменные Rule объекты.
 *
 * Преобразует validation_rules из модели Path в массив доменных Rule объектов,
 * учитывая data_type, is_required и cardinality.
 *
 * @package App\Domain\Blueprint\Validation
 */
interface PathValidationRulesConverterInterface
{
    /**
     * Преобразовать validation_rules из Path в доменные Rule объекты.
     *
     * Преобразует правила валидации с учётом:
     * - data_type: определяет базовый тип валидации (string, integer, numeric, boolean, date)
     * - is_required: добавляет RequiredRule или NullableRule (только для cardinality: 'one')
     * - cardinality: для 'many' возвращает правила для элементов массива (без required/nullable,
     *   так как они применяются к самому массиву в BlueprintContentValidator)
     * - validation_rules: преобразует min/max и pattern в соответствующие Rule объекты
     *
     * @param array<string, mixed>|null $validationRules Правила валидации из Path (может быть null)
     * @param string $dataType Тип данных Path (string, text, int, float, bool, date, datetime, json, ref)
     * @param bool $isRequired Обязательное ли поле (для cardinality: 'one')
     * @param string $cardinality Кардинальность: 'one' или 'many'
     * @param string|null $fieldName Имя поля (последний сегмент full_path) для использования в unique/exists правилах
     * @return list<\App\Domain\Blueprint\Validation\Rules\Rule> Массив доменных Rule объектов
     *         Для cardinality: 'one' - правила для самого поля
     *         Для cardinality: 'many' - правила для элементов массива (без RequiredRule/NullableRule)
     */
    public function convert(
        ?array $validationRules,
        string $dataType,
        bool $isRequired,
        string $cardinality,
        ?string $fieldName = null
    ): array;
}

