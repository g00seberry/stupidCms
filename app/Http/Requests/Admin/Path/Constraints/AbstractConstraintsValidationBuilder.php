<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path\Constraints;

use App\Models\Path;

/**
 * Абстрактный базовый класс для билдеров правил валидации constraints Path.
 *
 * Предоставляет общую функциональность и шаблонные методы,
 * которые могут быть переопределены в конкретных реализациях.
 *
 * @package App\Http\Requests\Admin\Path\Constraints
 */
abstract class AbstractConstraintsValidationBuilder implements ConstraintsValidationBuilderInterface
{
    /**
     * Получить правила валидации для constraints при создании Path (StorePathRequest).
     *
     * Базовая реализация проверяет, соответствует ли data_type поддерживаемому типу.
     * Если нет - возвращает правила, запрещающие constraints.
     * Если да - делегирует построение правил методу buildRulesForSupportedDataType().
     *
     * @param string $dataType Тип данных поля Path
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForStore(string $dataType): array
    {
        if ($dataType !== $this->getSupportedDataType()) {
            return $this->buildProhibitedRules();
        }

        return $this->buildRulesForSupportedDataType();
    }

    /**
     * Получить правила валидации для constraints при обновлении Path (UpdatePathRequest).
     *
     * Базовая реализация проверяет, соответствует ли data_type поддерживаемому типу.
     * Если нет - возвращает правила, запрещающие constraints.
     * Если да - делегирует построение правил методу buildUpdateRulesForSupportedDataType().
     *
     * @param string $dataType Текущий тип данных поля Path
     * @param Path|null $path Текущий Path из route
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForUpdate(string $dataType, ?Path $path): array
    {
        if ($dataType !== $this->getSupportedDataType()) {
            return $this->buildProhibitedRules();
        }

        return $this->buildUpdateRulesForSupportedDataType($path);
    }

    /**
     * Построить правила валидации для поддерживаемого типа данных при создании.
     *
     * Метод должен быть реализован в подклассах для построения конкретных правил валидации.
     * Этот метод вызывается только если data_type соответствует getSupportedDataType().
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    abstract protected function buildRulesForSupportedDataType(): array;

    /**
     * Построить правила валидации для поддерживаемого типа данных при обновлении.
     *
     * Метод должен быть реализован в подклассах для построения конкретных правил валидации.
     * Этот метод вызывается только если data_type соответствует getSupportedDataType().
     *
     * @param Path|null $path Текущий Path из route
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    abstract protected function buildUpdateRulesForSupportedDataType(?Path $path): array;

    /**
     * Построить правила, запрещающие использование constraints.
     *
     * Используется когда data_type не соответствует поддерживаемому типу билдера.
     * Запрещает передачу любых constraints для данного типа данных.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function buildProhibitedRules(): array
    {
        return [
            'constraints' => ['prohibited'],
        ];
    }

    /**
     * Получить базовое правило для constraints как массива.
     *
     * Вспомогательный метод для использования в подклассах.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function getBaseConstraintsArrayRule(): array
    {
        return [
            'constraints' => ['nullable', 'array'],
        ];
    }
}

