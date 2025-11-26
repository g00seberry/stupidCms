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
     * (например, 'required', 'string', 'min:1', 'max:500', 'regex:/pattern/').
     * Также добавляет базовые типы данных (string, integer, numeric, boolean, date, array)
     * на основе data_type из Path.
     *
     * @param \App\Domain\Blueprint\Validation\Rules\RuleSet $ruleSet Набор доменных правил
     * @param array<string, string> $dataTypes Маппинг путей полей на типы данных
     *         (например, ['content_json.title' => 'string', 'content_json.count' => 'int'])
     * @return array<string, array<int, string>> Массив правил валидации Laravel,
     *         где ключи - пути полей, значения - массивы строк правил
     */
    public function adapt(RuleSet $ruleSet, array $dataTypes = []): array;
}

