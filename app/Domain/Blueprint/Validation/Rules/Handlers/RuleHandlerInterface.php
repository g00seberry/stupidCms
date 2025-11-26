<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Интерфейс обработчика правила валидации.
 *
 * Преобразует доменное Rule в массив Laravel правил валидации.
 * Каждый handler отвечает за конкретный тип правила.
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
interface RuleHandlerInterface
{
    /**
     * Проверить, поддерживает ли handler указанный тип правила.
     *
     * @param string $ruleType Тип правила (min, max, pattern, required_if и т.д.)
     * @return bool
     */
    public function supports(string $ruleType): bool;

    /**
     * Обработать правило и преобразовать в Laravel правила валидации.
     *
     * @param \App\Domain\Blueprint\Validation\Rules\Rule $rule Доменное правило
     * @param string $dataType Тип данных поля (string, int, float и т.д.)
     * @return array<int, string> Массив строк Laravel правил валидации
     */
    public function handle(Rule $rule, string $dataType): array;
}

