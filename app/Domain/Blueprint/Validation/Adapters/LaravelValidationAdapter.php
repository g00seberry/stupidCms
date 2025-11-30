<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Adapters;

use App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerRegistry;
use App\Domain\Blueprint\Validation\Rules\RuleSet;

/**
 * Адаптер для преобразования доменных RuleSet в Laravel правила валидации.
 *
 * Преобразует доменные Rule объекты в строки правил валидации Laravel
 * через систему handlers. Не добавляет базовые типы данных автоматически.
 * Пользователь сам отвечает за указание всех необходимых правил.
 *
 * @package App\Domain\Blueprint\Validation\Adapters
 */
final class LaravelValidationAdapter implements LaravelValidationAdapterInterface
{
    /**
     * @param \App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerRegistry $registry Реестр обработчиков правил
     */
    public function __construct(
        private readonly RuleHandlerRegistry $registry
    ) {}

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
    public function adapt(RuleSet $ruleSet, array $dataTypes = []): array
    {
        $laravelRules = [];

        foreach ($ruleSet->getAllRules() as $field => $rules) {
            $fieldRules = [];

            // Преобразуем каждое правило в строку Laravel через handlers
            foreach ($rules as $rule) {
                $ruleType = $rule->getType();
                $handler = $this->registry->getHandler($ruleType);

                if ($handler === null) {
                    throw new \InvalidArgumentException("No handler found for rule type: {$ruleType}");
                }

                // Обрабатываем правило через handler
                $laravelRuleStrings = $handler->handle($rule);
                $fieldRules = array_merge($fieldRules, $laravelRuleStrings);
            }

            if (! empty($fieldRules)) {
                $laravelRules[$field] = $fieldRules;
            }
        }

        return $laravelRules;
    }


}

