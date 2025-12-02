<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Models\Blueprint;
use App\Domain\Blueprint\Validation\FieldPathBuilder;
use App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface;
use App\Domain\Blueprint\Validation\Rules\RuleSet;

/**
 * Доменный сервис валидации контента Entry на основе Blueprint.
 *
 * Строит RuleSet для поля content_json на основе структуры Path в Blueprint.
 * Преобразует full_path в точечную нотацию и применяет validation_rules из каждого Path.
 * Не выполняет проверок совместимости правил - пользователь сам настраивает правила.
 *
 * @package App\Domain\Blueprint\Validation
 */
final class EntryValidationService implements EntryValidationServiceInterface
{
    /**
     * @param \App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface $converter Конвертер правил валидации
     * @param \App\Domain\Blueprint\Validation\FieldPathBuilder $fieldPathBuilder Построитель путей полей
     */
    public function __construct(
        private readonly PathValidationRulesConverterInterface $converter,
        private readonly FieldPathBuilder $fieldPathBuilder
    ) {}

    /**
     * Построить RuleSet для Blueprint.
     *
     * Анализирует все Path в blueprint и преобразует их validation_rules
     * в доменный RuleSet для поля content_json.
     * Преобразует full_path в точечную нотацию с учётом cardinality для построения путей.
     * Пути для правил строятся через FieldPathBuilder, который учитывает специфику правил
     * (например, distinct для массивов должен применяться к элементам массива).
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для валидации
     * @return \App\Domain\Blueprint\Validation\Rules\RuleSet Набор правил валидации
     */
    public function buildRulesFor(Blueprint $blueprint): RuleSet
    {
        $ruleSet = new RuleSet();

        // Загружаем все Path из blueprint (включая скопированные)
        $paths = $blueprint->paths()
            ->select(['id', 'name', 'full_path', 'cardinality', 'validation_rules'])
            ->orderByRaw('LENGTH(full_path), full_path')
            ->get();

        if ($paths->isEmpty()) {
            return $ruleSet;
        }

        // Создаём маппинг full_path → cardinality для FieldPathBuilder
        $pathCardinalities = [];
        foreach ($paths as $path) {
            $pathCardinalities[$path->full_path] = $path->cardinality;
        }

        // Обрабатываем каждый Path
        foreach ($paths as $path) {
            // Преобразуем validation_rules в Rule объекты
            $fieldRules = $this->converter->convert($path->validation_rules);

            // Добавляем все правила для поля
            // Для каждого правила строим путь с учётом его специфики
            foreach ($fieldRules as $rule) {
                $fieldPath = $this->fieldPathBuilder->buildFieldPathForRule(
                    $path->full_path,
                    $pathCardinalities,
                    $rule,
                    $path->cardinality
                );
                $ruleSet->addRule($fieldPath, $rule);
            }
        }

        return $ruleSet;
    }
}

