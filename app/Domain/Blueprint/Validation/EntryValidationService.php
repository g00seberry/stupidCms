<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Models\Blueprint;
use App\Models\Path;
use App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface;
use App\Domain\Blueprint\Validation\Rules\RuleFactory;
use App\Domain\Blueprint\Validation\Rules\RuleSet;

/**
 * Доменный сервис валидации контента Entry на основе Blueprint.
 *
 * Строит RuleSet для поля content_json на основе структуры Path в Blueprint.
 * Преобразует full_path в точечную нотацию и применяет validation_rules из каждого Path.
 *
 * @package App\Domain\Blueprint\Validation
 */
final class EntryValidationService implements EntryValidationServiceInterface
{
    /**
     * @param \App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface $converter Конвертер правил валидации
     * @param \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory Фабрика для создания правил
     */
    public function __construct(
        private readonly PathValidationRulesConverterInterface $converter,
        private readonly RuleFactory $ruleFactory
    ) {}

    /**
     * Построить RuleSet для Blueprint.
     *
     * Анализирует все Path в blueprint и преобразует их validation_rules
     * в доменный RuleSet для поля content_json.
     * Учитывает:
     * - data_type каждого Path (string, int, float, bool, date, datetime, json, ref)
     * - is_required (RequiredRule или NullableRule)
     * - cardinality (one или many)
     * - validation_rules (min, max, pattern и т.д.)
     * - вложенность путей (full_path → точечная нотация)
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для валидации
     * @return \App\Domain\Blueprint\Validation\Rules\RuleSet Набор правил валидации
     */
    public function buildRulesFor(Blueprint $blueprint): RuleSet
    {
        $ruleSet = new RuleSet();

        // Загружаем все Path из blueprint (включая скопированные)
        $paths = $blueprint->paths()
            ->select(['id', 'name', 'full_path', 'data_type', 'cardinality', 'is_required', 'validation_rules'])
            ->orderByRaw('LENGTH(full_path), full_path')
            ->get();

        if ($paths->isEmpty()) {
            return $ruleSet;
        }

        // Обрабатываем каждый Path
        foreach ($paths as $path) {
            $fieldPath = $this->buildFieldPath($path->full_path);

            // Для cardinality: 'many' создаём правила для массива и для элементов
            if ($path->cardinality === 'many') {
                // Правила для самого массива (required/nullable)
                // Правило "array" будет добавлено в адаптере/FormRequest, так как это Laravel-специфично
                if ($path->is_required) {
                    $ruleSet->addRule($fieldPath, $this->ruleFactory->createRequiredRule());
                } else {
                    $ruleSet->addRule($fieldPath, $this->ruleFactory->createNullableRule());
                }

                // Правила для самого массива (array_min_items, array_max_items)
                // Эти правила применяются к массиву, а не к элементам
                $arrayUniqueRule = null;
                if ($path->validation_rules !== null && $path->validation_rules !== []) {
                    foreach ($path->validation_rules as $key => $value) {
                        match ($key) {
                            'array_min_items' => $ruleSet->addRule($fieldPath, $this->ruleFactory->createArrayMinItemsRule((int) $value)),
                            'array_max_items' => $ruleSet->addRule($fieldPath, $this->ruleFactory->createArrayMaxItemsRule((int) $value)),
                            'array_unique' => $arrayUniqueRule = $this->ruleFactory->createArrayUniqueRule(), // Извлекаем для применения к элементам
                            default => null, // Остальные правила обрабатываются для элементов
                        };
                    }
                }

                // Правила для элементов массива (min, max, pattern)
                // Исключаем правила для массива, так как они применяются к самому массиву
                $elementValidationRules = $path->validation_rules;
                if ($elementValidationRules !== null && $elementValidationRules !== []) {
                    unset($elementValidationRules['array_min_items'], $elementValidationRules['array_max_items'], $elementValidationRules['array_unique']);
                }

                $elementRules = $this->converter->convert(
                    $elementValidationRules,
                    $path->data_type,
                    false, // Элементы массива не могут быть required
                    'one' // Элементы обрабатываются как одиночные значения
                );

                // Добавляем правила для элементов массива (без RequiredRule/NullableRule)
                foreach ($elementRules as $rule) {
                    // Пропускаем RequiredRule и NullableRule для элементов массива
                    if (! in_array($rule->getType(), ['required', 'nullable'], true)) {
                        $ruleSet->addRule($fieldPath.'.*', $rule);
                    }
                }

                // Добавляем правило array_unique для элементов массива
                if ($arrayUniqueRule !== null) {
                    $ruleSet->addRule($fieldPath.'.*', $arrayUniqueRule);
                }
            } else {
                // Для cardinality: 'one' создаём правила для самого поля
                $fieldRules = $this->converter->convert(
                    $path->validation_rules,
                    $path->data_type,
                    $path->is_required,
                    'one'
                );

                // Добавляем все правила для поля
                foreach ($fieldRules as $rule) {
                    $ruleSet->addRule($fieldPath, $rule);
                }
            }
        }

        return $ruleSet;
    }

    /**
     * Построить путь поля в точечной нотации для валидации.
     *
     * Преобразует full_path из Path в путь для content_json.
     * Например: 'title' → 'content_json.title', 'author.name' → 'content_json.author.name'
     *
     * @param string $fullPath Полный путь из Path (например, 'author.contacts.phone')
     * @return string Путь в точечной нотации для валидации (например, 'content_json.author.contacts.phone')
     */
    private function buildFieldPath(string $fullPath): string
    {
        return 'content_json.'.$fullPath;
    }
}

