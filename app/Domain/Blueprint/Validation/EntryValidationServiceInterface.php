<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Models\Blueprint;
use App\Domain\Blueprint\Validation\Rules\RuleSet;

/**
 * Интерфейс доменного сервиса валидации Entry.
 *
 * Определяет контракт для построения правил валидации и валидации контента
 * на основе Blueprint, независимо от Laravel.
 *
 * @package App\Domain\Blueprint\Validation
 */
interface EntryValidationServiceInterface
{
    /**
     * Построить RuleSet для Blueprint.
     *
     * Анализирует все Path в blueprint и преобразует их validation_rules
     * в доменный RuleSet для поля content_json.
     * Автоматически создаёт правила типов данных на основе data_type,
     * если они не указаны явно в validation_rules.
     *
     * Учитывает:
     * - data_type каждого Path (автоматически создаёт правила типов: string, integer, numeric, boolean, date, array)
     * - required (из validation_rules['required'], RequiredRule или NullableRule)
     * - cardinality (one или many)
     * - validation_rules (required, min, max, pattern и т.д.)
     * - вложенность путей (full_path → точечная нотация)
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для валидации
     * @return \App\Domain\Blueprint\Validation\Rules\RuleSet Набор правил валидации
     */
    public function buildRulesFor(Blueprint $blueprint): RuleSet;
}

