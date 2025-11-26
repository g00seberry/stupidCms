<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

/**
 * Реестр обработчиков правил валидации.
 *
 * Управляет регистрацией и получением handlers для различных типов правил.
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class RuleHandlerRegistry
{
    /**
     * @var array<string, RuleHandlerInterface> Массив handlers, где ключ - тип правила
     */
    private array $handlers = [];

    /**
     * Зарегистрировать handler для указанного типа правила.
     *
     * @param string $ruleType Тип правила (min, max, pattern и т.д.)
     * @param \App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface $handler Handler для обработки правила
     * @return void
     */
    public function register(string $ruleType, RuleHandlerInterface $handler): void
    {
        $this->handlers[$ruleType] = $handler;
    }

    /**
     * Получить handler для указанного типа правила.
     *
     * @param string $ruleType Тип правила
     * @return \App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface|null Handler или null, если не найден
     */
    public function getHandler(string $ruleType): ?RuleHandlerInterface
    {
        return $this->handlers[$ruleType] ?? null;
    }

    /**
     * Проверить, зарегистрирован ли handler для указанного типа правила.
     *
     * @param string $ruleType Тип правила
     * @return bool
     */
    public function hasHandler(string $ruleType): bool
    {
        return isset($this->handlers[$ruleType]);
    }

    /**
     * Получить все зарегистрированные типы правил.
     *
     * @return list<string> Массив типов правил
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->handlers);
    }
}

