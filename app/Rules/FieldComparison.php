<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Правило валидации: сравнение значения поля с другим полем или константой.
 *
 * Поддерживает операторы: '>=', '<=', '>', '<', '==', '!='
 * Автоматически определяет тип данных (даты, числа, строки) для корректного сравнения.
 *
 * @package App\Rules
 */
final class FieldComparison implements ValidationRule, DataAwareRule
{
    /**
     * @var array<string, mixed> Данные запроса для валидации
     */
    private array $data = [];

    /**
     * @param string $operator Оператор сравнения ('>=', '<=', '>', '<', '==', '!=')
     * @param string $otherField Путь к другому полю для сравнения (например, 'content_json.start_date')
     * @param mixed|null $constantValue Константное значение для сравнения (если указано, используется вместо otherField)
     */
    public function __construct(
        private readonly string $operator,
        private readonly string $otherField,
        private readonly mixed $constantValue = null
    ) {}

    /**
     * Установить данные для валидации.
     *
     * @param array<string, mixed> $data Данные запроса
     * @return static
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Выполнить правило валидации.
     *
     * Сравнивает значение текущего поля с другим полем или константой.
     * Автоматически определяет тип данных для корректного сравнения.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Если текущее значение отсутствует, пропускаем валидацию
        // (required/nullable обработают это)
        if ($value === null) {
            return;
        }

        // Получаем значение для сравнения
        $compareValue = $this->constantValue;
        if ($compareValue === null) {
            // Если константное значение не указано, используем другое поле
            if ($this->otherField === '') {
                // Если поле не указано, пропускаем валидацию
                return;
            }
            $compareValue = data_get($this->data, $this->otherField);
        }

        // Если сравниваемое значение отсутствует (и это не константа), пропускаем валидацию
        // (это позволяет другим правилам обработать required/nullable)
        if ($compareValue === null && $this->constantValue === null) {
            return;
        }

        // Выполняем сравнение
        $result = match ($this->operator) {
            '>=' => $this->compare($value, $compareValue) >= 0,
            '<=' => $this->compare($value, $compareValue) <= 0,
            '>' => $this->compare($value, $compareValue) > 0,
            '<' => $this->compare($value, $compareValue) < 0,
            '==' => $this->compare($value, $compareValue) === 0,
            '!=' => $this->compare($value, $compareValue) !== 0,
            default => false,
        };

        if (! $result) {
            $fieldName = $this->constantValue !== null
                ? $this->formatValue($this->constantValue)
                : $this->otherField;
            
            $fail("The :attribute must be {$this->operator} {$fieldName}.");
        }
    }

    /**
     * Сравнить два значения с учётом их типов.
     *
     * Поддерживает сравнение дат, чисел и строк.
     *
     * @param mixed $a Первое значение
     * @param mixed $b Второе значение
     * @return int Результат сравнения: -1 если $a < $b, 0 если равны, 1 если $a > $b
     */
    private function compare(mixed $a, mixed $b): int
    {
        // Для дат используем сравнение через Carbon
        if ($a instanceof \DateTimeInterface && $b instanceof \DateTimeInterface) {
            return $a <=> $b;
        }

        // Пытаемся преобразовать строки в даты
        if (is_string($a) && is_string($b)) {
            try {
                $dateA = Carbon::parse($a);
                $dateB = Carbon::parse($b);
                return $dateA <=> $dateB;
            } catch (\Exception $e) {
                // Не даты, продолжаем стандартное сравнение
            }
        }

        // Для чисел используем числовое сравнение
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        // Стандартное сравнение для остальных типов
        return $a <=> $b;
    }

    /**
     * Форматировать значение для отображения в сообщении об ошибке.
     *
     * @param mixed $value Значение для форматирования
     * @return string Отформатированное значение
     */
    private function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }
}

