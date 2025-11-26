<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Реализация фабрики правил валидации.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class RuleFactoryImpl implements RuleFactory
{
    /**
     * Создать правило минимального значения/длины.
     *
     * @param mixed $value Значение минимума
     * @param string $dataType Тип данных (string, text, int, float)
     * @return \App\Domain\Blueprint\Validation\Rules\MinRule
     */
    public function createMinRule(mixed $value, string $dataType): MinRule
    {
        return new MinRule($value, $dataType);
    }

    /**
     * Создать правило максимального значения/длины.
     *
     * @param mixed $value Значение максимума
     * @param string $dataType Тип данных (string, text, int, float)
     * @return \App\Domain\Blueprint\Validation\Rules\MaxRule
     */
    public function createMaxRule(mixed $value, string $dataType): MaxRule
    {
        return new MaxRule($value, $dataType);
    }

    /**
     * Создать правило регулярного выражения.
     *
     * Валидирует и нормализует паттерн перед созданием правила.
     *
     * @param mixed $pattern Паттерн регулярного выражения
     * @return \App\Domain\Blueprint\Validation\Rules\PatternRule
     */
    public function createPatternRule(mixed $pattern): PatternRule
    {
        if (! is_string($pattern) || $pattern === '') {
            // Возвращаем паттерн, который принимает всё (для безопасности)
            return new PatternRule('.*');
        }

        return new PatternRule($pattern);
    }

    /**
     * Создать правило обязательности поля.
     *
     * @return \App\Domain\Blueprint\Validation\Rules\RequiredRule
     */
    public function createRequiredRule(): RequiredRule
    {
        return new RequiredRule();
    }

    /**
     * Создать правило опциональности поля (nullable).
     *
     * @return \App\Domain\Blueprint\Validation\Rules\NullableRule
     */
    public function createNullableRule(): NullableRule
    {
        return new NullableRule();
    }

    /**
     * Создать правило минимального количества элементов массива.
     *
     * @param int $value Минимальное количество элементов
     * @return \App\Domain\Blueprint\Validation\Rules\ArrayMinItemsRule
     */
    public function createArrayMinItemsRule(int $value): ArrayMinItemsRule
    {
        return new ArrayMinItemsRule($value);
    }

    /**
     * Создать правило максимального количества элементов массива.
     *
     * @param int $value Максимальное количество элементов
     * @return \App\Domain\Blueprint\Validation\Rules\ArrayMaxItemsRule
     */
    public function createArrayMaxItemsRule(int $value): ArrayMaxItemsRule
    {
        return new ArrayMaxItemsRule($value);
    }

    /**
     * Создать условное правило валидации.
     *
     * @param string $type Тип правила ('required_if', 'prohibited_unless', 'required_unless', 'prohibited_if')
     * @param string $field Путь к полю условия
     * @param mixed $value Значение для условия
     * @param string|null $operator Оператор сравнения (по умолчанию '==')
     * @return \App\Domain\Blueprint\Validation\Rules\ConditionalRule
     */
    public function createConditionalRule(string $type, string $field, mixed $value, ?string $operator = null): ConditionalRule
    {
        return new ConditionalRule($type, $field, $value, $operator);
    }

    /**
     * Создать правило уникальности значения.
     *
     * @param string $table Таблица для проверки
     * @param string $column Колонка для проверки
     * @param string|null $exceptColumn Колонка для исключения
     * @param mixed $exceptValue Значение для исключения
     * @param string|null $whereColumn Дополнительная колонка для WHERE
     * @param mixed $whereValue Значение для WHERE
     * @return \App\Domain\Blueprint\Validation\Rules\UniqueRule
     */
    public function createUniqueRule(
        string $table,
        string $column = 'id',
        ?string $exceptColumn = null,
        mixed $exceptValue = null,
        ?string $whereColumn = null,
        mixed $whereValue = null
    ): UniqueRule {
        return new UniqueRule($table, $column, $exceptColumn, $exceptValue, $whereColumn, $whereValue);
    }

    /**
     * Создать правило существования значения.
     *
     * @param string $table Таблица для проверки
     * @param string $column Колонка для проверки
     * @param string|null $whereColumn Дополнительная колонка для WHERE
     * @param mixed $whereValue Значение для WHERE
     * @return \App\Domain\Blueprint\Validation\Rules\ExistsRule
     */
    public function createExistsRule(
        string $table,
        string $column = 'id',
        ?string $whereColumn = null,
        mixed $whereValue = null
    ): ExistsRule {
        return new ExistsRule($table, $column, $whereColumn, $whereValue);
    }

    /**
     * Создать правило уникальности элементов массива.
     *
     * @return \App\Domain\Blueprint\Validation\Rules\ArrayUniqueRule
     */
    public function createArrayUniqueRule(): ArrayUniqueRule
    {
        return new ArrayUniqueRule();
    }
}

