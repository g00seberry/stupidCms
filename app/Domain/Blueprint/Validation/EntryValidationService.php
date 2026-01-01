<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Models\Blueprint;
use App\Domain\Blueprint\Validation\FieldPathBuilder;
use App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface;
use App\Domain\Blueprint\Validation\Rules\RuleFactory;
use App\Domain\Blueprint\Validation\Rules\RuleSet;

/**
 * Доменный сервис валидации контента Entry на основе Blueprint.
 *
 * Строит RuleSet для поля data_json на основе структуры Path в Blueprint.
 * Преобразует full_path в точечную нотацию и применяет validation_rules из каждого Path.
 * Автоматически создаёт правила типов данных на основе data_type, если они не указаны явно.
 *
 * @package App\Domain\Blueprint\Validation
 */
final class EntryValidationService implements EntryValidationServiceInterface
{
    /**
     * @param \App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface $converter Конвертер правил валидации
     * @param \App\Domain\Blueprint\Validation\FieldPathBuilder $fieldPathBuilder Построитель путей полей
     * @param \App\Domain\Blueprint\Validation\DataTypeMapper $dataTypeMapper Маппер типов данных
     * @param \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory Фабрика правил
     * @param \App\Services\Path\Constraints\PathConstraintsBuilderRegistry $constraintsBuilderRegistry Регистр билдеров constraints
     */
    public function __construct(
        private readonly PathValidationRulesConverterInterface $converter,
        private readonly FieldPathBuilder $fieldPathBuilder,
        private readonly DataTypeMapper $dataTypeMapper,
        private readonly RuleFactory $ruleFactory,
        private readonly \App\Services\Path\Constraints\PathConstraintsBuilderRegistry $constraintsBuilderRegistry
    ) {}

    /**
     * Построить RuleSet для Blueprint.
     *
     * Анализирует все Path в blueprint и преобразует их validation_rules
     * в доменный RuleSet для поля data_json.
     * Автоматически добавляет правила типов данных на основе data_type,
     * если они не указаны явно в validation_rules.
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для валидации
     * @return \App\Domain\Blueprint\Validation\Rules\RuleSet Набор правил валидации
     */
    public function buildRulesFor(Blueprint $blueprint): RuleSet
    {
        $ruleSet = new RuleSet();

        // Загружаем все Path из blueprint (включая скопированные)
        // Теперь загружаем также data_type для автоматического создания правил типов
        // И загружаем constraints для всех поддерживаемых типов данных через регистр
        $relationsToLoad = $this->getConstraintsRelationsToLoad();
        
        $query = $blueprint->paths()
            ->select(['id', 'name', 'full_path', 'cardinality', 'data_type', 'validation_rules']);
        
        if (!empty($relationsToLoad)) {
            $query->with($relationsToLoad);
        }
        
        $paths = $query->orderByRaw('LENGTH(full_path), full_path')
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
            
            $fieldPath = $this->fieldPathBuilder->buildFieldPath(
                $path->full_path,
                $pathCardinalities,
            );
            
            // Если cardinality = 'many', добавляем правило array для самого поля
            if ($path->cardinality === 'many') {
                $ruleSet->addRule($fieldPath, $this->ruleFactory->createTypeRule('array'));
            }
            
            // Добавляем все правила для поля (или элементов массива, если cardinality = 'many')
            foreach ($fieldRules as $rule) {
                $ruleSet->addRule($fieldPath, $rule);
            }
            
            $validationType = $this->dataTypeMapper->mapToValidationType($path->data_type, $path->cardinality);
            if ($validationType !== null) {
                // Для cardinality = 'many' правило типа применяется к элементам массива (с .*)
                // Для cardinality = 'one' правило типа применяется к самому полю
                if ($path->cardinality === 'many') {
                    // Для полей с cardinality = 'many' правило типа применяется к элементам массива
                    $typeFieldPath = $fieldPath . ValidationConstants::ARRAY_ELEMENT_WILDCARD;
                    $ruleSet->addRule($typeFieldPath, $this->ruleFactory->createTypeRule($validationType));
                } else {
                    // Для полей с cardinality = 'one' правило типа применяется к самому полю
                    $ruleSet->addRule($fieldPath, $this->ruleFactory->createTypeRule($validationType));
                }
            }

            // Добавляем валидацию constraints через билдеры
            $constraintsBuilder = $this->constraintsBuilderRegistry->getBuilder($path->data_type);
            if ($constraintsBuilder !== null) {
                $validationRule = $constraintsBuilder->buildValidationRule(
                    $path,
                    $this->ruleFactory,
                    $fieldPath,
                    $path->cardinality
                );

                if ($validationRule !== null) {
                    // Для cardinality = 'many' правило применяется к элементам массива
                    // Для cardinality = 'one' правило применяется к самому полю
                    if ($path->cardinality === 'many') {
                        $refFieldPath = $fieldPath . ValidationConstants::ARRAY_ELEMENT_WILDCARD;
                        $ruleSet->addRule($refFieldPath, $validationRule);
                    } else {
                        $ruleSet->addRule($fieldPath, $validationRule);
                    }
                }
            }
        }

        return $ruleSet;
    }

    /**
     * Получить список имён связей для eager loading constraints.
     *
     * Собирает имена связей из всех зарегистрированных билдеров constraints
     * для универсальной загрузки через with().
     *
     * @return array<string> Массив имён связей (например, ['refConstraints'])
     */
    private function getConstraintsRelationsToLoad(): array
    {
        $relationsToLoad = [];

        foreach ($this->constraintsBuilderRegistry->getAllBuilders() as $builder) {
            $relationName = $builder->getRelationName();
            if ($relationName !== '') {
                $relationsToLoad[] = $relationName;
            }
        }

        return $relationsToLoad;
    }

}

