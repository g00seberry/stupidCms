<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Models\Blueprint;

/**
 * Интерфейс валидатора контента Entry на основе Blueprint.
 *
 * Определяет контракт для построения правил валидации Laravel
 * для поля content_json на основе структуры Path в Blueprint.
 *
 * @package App\Domain\Blueprint\Validation
 */
interface BlueprintContentValidatorInterface
{
    /**
     * Построить правила валидации для content_json на основе Path blueprint.
     *
     * Анализирует все Path в blueprint и преобразует их validation_rules
     * в массив правил валидации Laravel для поля content_json.
     * Учитывает:
     * - data_type каждого Path (string, int, float, bool, datetime, json, ref)
     * - validation_rules['required'] (required или nullable)
     * - cardinality (one или many)
     * - validation_rules (min, max, pattern и т.д.)
     * - вложенность путей (full_path → точечная нотация)
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для валидации
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     *         Массив правил валидации, где ключи - это пути в точечной нотации
     *         (например, 'content_json.title', 'content_json.author.name'),
     *         а значения - правила Laravel (массив строк или ValidationRule)
     */
    public function buildRules(Blueprint $blueprint): array;
}

