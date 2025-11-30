<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Adapters;

use App\Domain\Blueprint\Validation\Rules\RuleSet;

/**
 * Интерфейс адаптера для преобразования доменных RuleSet в Laravel правила валидации.
 *
 * Преобразует доменные Rule объекты в массив строк правил валидации Laravel.
 *
 * @package App\Domain\Blueprint\Validation\Adapters
 */
interface LaravelValidationAdapterInterface
{
    /**
     * Преобразовать RuleSet в массив правил Laravel.
     *
     * Преобразует доменные Rule объекты в строки правил валидации Laravel
     * (например, 'required', 'min:1', 'max:500', 'regex:/pattern/').
     * Не добавляет базовые типы данных автоматически.
     *
     * @param \App\Domain\Blueprint\Validation\Rules\RuleSet $ruleSet Набор доменных правил
     * @param array<string, string> $dataTypes Маппинг путей полей на типы данных (не используется, оставлен для обратной совместимости)
     * @return array<string, array<int, string|object>> Массив правил валидации Laravel,
     *         где ключи - пути полей, значения - массивы строк правил
     */
    public function adapt(RuleSet $ruleSet, array $dataTypes = []): array;
}

