<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Models\Blueprint;
use App\Domain\Blueprint\Validation\FieldPathBuilder;
use App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface;
use App\Domain\Blueprint\Validation\Rules\RuleFactory;
use App\Domain\Blueprint\Validation\Rules\RuleSet;
use App\Domain\Blueprint\Validation\ValidationConstants;

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
     * @param \App\Domain\Blueprint\Validation\FieldPathBuilder $fieldPathBuilder Построитель путей полей
     */
    public function __construct(
        private readonly PathValidationRulesConverterInterface $converter,
        private readonly RuleFactory $ruleFactory,
        private readonly FieldPathBuilder $fieldPathBuilder
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
     * - вложенные поля внутри массивов (замена сегментов на * для cardinality: 'many')
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

        // Создаём маппинг full_path → cardinality для определения родительских массивов
        $pathCardinalities = [];
        foreach ($paths as $path) {
            $pathCardinalities[$path->full_path] = $path->cardinality;
        }

        // Обрабатываем каждый Path
        foreach ($paths as $path) {
            $fieldPath = $this->fieldPathBuilder->buildFieldPath($path->full_path, $pathCardinalities);

            // Для cardinality: 'many' создаём правила для массива и для элементов
            if ($path->cardinality === ValidationConstants::CARDINALITY_MANY) {
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

                // Извлекаем имя поля из full_path (последний сегмент)
                $fieldName = $path->name;
                
                $elementRules = $this->converter->convert(
                    $elementValidationRules,
                    $path->data_type,
                    false, // Элементы массива не могут быть required
                    ValidationConstants::CARDINALITY_ONE, // Элементы обрабатываются как одиночные значения
                    $fieldName
                );

                // Добавляем правила для элементов массива (без RequiredRule/NullableRule)
                // ВАЖНО: Даже если нет validation_rules, нужно добавить пустое правило для элементов,
                // чтобы LaravelValidationAdapter мог добавить базовый тип (например, 'array' для json)
                $hasElementRules = false;
                foreach ($elementRules as $rule) {
                    // Пропускаем RequiredRule и NullableRule для элементов массива
                    if (! in_array($rule->getType(), ValidationConstants::getRequiredNullableRules(), true)) {
                        $ruleSet->addRule($fieldPath.ValidationConstants::ARRAY_ELEMENT_WILDCARD, $rule);
                        $hasElementRules = true;
                    }
                }

                // Если нет правил для элементов, но data_type: 'json', добавляем placeholder правило,
                // чтобы LaravelValidationAdapter мог добавить базовый тип 'array' для элементов
                if (! $hasElementRules && $path->data_type === ValidationConstants::DATA_TYPE_JSON) {
                    $ruleSet->addRule($fieldPath.ValidationConstants::ARRAY_ELEMENT_WILDCARD, $this->ruleFactory->createNullableRule());
                }

                // Добавляем правило array_unique для элементов массива
                if ($arrayUniqueRule !== null) {
                    $ruleSet->addRule($fieldPath.ValidationConstants::ARRAY_ELEMENT_WILDCARD, $arrayUniqueRule);
                }
            } else {
                // Для cardinality: 'one' создаём правила для самого поля
                // Извлекаем имя поля из full_path (последний сегмент)
                $fieldName = $path->name;
                
                $fieldRules = $this->converter->convert(
                    $path->validation_rules,
                    $path->data_type,
                    $path->is_required,
                    ValidationConstants::CARDINALITY_ONE,
                    $fieldName
                );

                // Добавляем все правила для поля
                foreach ($fieldRules as $rule) {
                    $ruleSet->addRule($fieldPath, $rule);
                }
            }
        }

        return $ruleSet;
    }
}

