<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Правило валидации: регулярное выражение (pattern).
 *
 * Применяется только к строковым типам данных (string, text).
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class PatternRule implements Rule
{
    /**
     * @param string $pattern Регулярное выражение (может быть с ограничителями или без)
     */
    public function __construct(
        private readonly string $pattern
    ) {}

    /**
     * Получить тип правила.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'pattern';
    }

    /**
     * Получить параметры правила.
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return [
            'pattern' => $this->pattern,
        ];
    }

    /**
     * Получить паттерн регулярного выражения.
     *
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }
}

