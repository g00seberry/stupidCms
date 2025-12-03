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
 * Строит RuleSet для поля content_json на основе структуры Path в Blueprint.
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
     */
    public function __construct(
        private readonly PathValidationRulesConverterInterface $converter,
        private readonly FieldPathBuilder $fieldPathBuilder,
        private readonly DataTypeMapper $dataTypeMapper,
        private readonly RuleFactory $ruleFactory
    ) {}

    /**
     * Построить RuleSet для Blueprint.
     *
     * Анализирует все Path в blueprint и преобразует их validation_rules
     * в доменный RuleSet для поля content_json.
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
        $paths = $blueprint->paths()
            ->select(['id', 'name', 'full_path', 'cardinality', 'data_type', 'validation_rules'])
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
            
            // Проверяем, есть ли явное правило типа в validation_rules
            $hasExplicitTypeRule = $this->hasExplicitTypeRule($path->validation_rules);
            
            $fieldPath = $this->fieldPathBuilder->buildFieldPath(
                $path->full_path,
                $pathCardinalities,
            );
            
            // Если cardinality = 'many', добавляем правило array для самого поля
            if ($path->cardinality === 'many') {
                $hasExplicitArrayRule = $this->hasExplicitArrayRule($path->validation_rules);
                if (! $hasExplicitArrayRule) {
                    // Путь для самого массива (без *)
                    $arrayFieldPath = $this->buildArrayFieldPath($path->full_path, $pathCardinalities);
                    $ruleSet->addRule($arrayFieldPath, $this->ruleFactory->createTypeRule('array'));
                }
            }
            
            // Если нет явного правила типа и data_type указан, создаём автоматически
            if (! $hasExplicitTypeRule && $path->data_type !== null) {
                $validationType = $this->dataTypeMapper->mapToValidationType($path->data_type, $path->cardinality);
                if ($validationType !== null) {
                    // Для cardinality = 'many' правило типа применяется к элементам массива
                    // Для cardinality = 'one' правило типа применяется к самому полю
                    $fieldRules[] = $this->ruleFactory->createTypeRule($validationType);
                }
            }
            
            // Добавляем все правила для поля (или элементов массива, если cardinality = 'many')
            foreach ($fieldRules as $rule) {
                $ruleSet->addRule($fieldPath, $rule);
            }
        }

        return $ruleSet;
    }

    /**
     * Проверить, есть ли явное правило типа в validation_rules.
     *
     * Проверяет наличие ключей 'type' или стандартных Laravel правил типов
     * (string, integer, numeric, boolean, date, array) в validation_rules.
     *
     * @param array<string, mixed>|null $validationRules Правила валидации
     * @return bool true, если найдено явное правило типа
     */
    private function hasExplicitTypeRule(?array $validationRules): bool
    {
        if ($validationRules === null || $validationRules === []) {
            return false;
        }

        // Проверяем наличие ключа 'type'
        if (isset($validationRules['type'])) {
            return true;
        }

        // Проверяем наличие стандартных Laravel правил типов
        $typeKeys = ['string', 'integer', 'int', 'numeric', 'boolean', 'bool', 'date', 'array'];
        foreach ($typeKeys as $typeKey) {
            if (isset($validationRules[$typeKey])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверить, есть ли явное правило array в validation_rules.
     *
     * Проверяет наличие ключа 'array' в validation_rules.
     *
     * @param array<string, mixed>|null $validationRules Правила валидации
     * @return bool true, если найдено явное правило array
     */
    private function hasExplicitArrayRule(?array $validationRules): bool
    {
        if ($validationRules === null || $validationRules === []) {
            return false;
        }

        return isset($validationRules['array']) || isset($validationRules['type']) && $validationRules['type'] === 'array';
    }

    /**
     * Построить путь для самого массива (без *).
     *
     * Строит путь для поля с cardinality = 'many', учитывая только родительские пути.
     * Не добавляет * для текущего поля.
     *
     * @param string $fullPath Полный путь из Path
     * @param array<string, string> $pathCardinalities Маппинг full_path → cardinality
     * @return string Путь для самого массива
     */
    private function buildArrayFieldPath(string $fullPath, array $pathCardinalities): string
    {
        $segments = explode('.', $fullPath);
        $resultSegments = [];
        $wildcardSegment = ltrim(ValidationConstants::ARRAY_ELEMENT_WILDCARD, '.');

        // Обрабатываем каждый сегмент пути, кроме последнего
        for ($i = 0; $i < count($segments) - 1; $i++) {
            // Строим путь до текущего сегмента для проверки cardinality
            $parentPath = implode('.', array_slice($segments, 0, $i));

            // Если это не первый сегмент, проверяем cardinality родительского пути
            if ($i > 0 && isset($pathCardinalities[$parentPath]) && $pathCardinalities[$parentPath] === ValidationConstants::CARDINALITY_MANY) {
                // Родительский путь - массив, заменяем текущий сегмент на '*'
                $resultSegments[] = $wildcardSegment;
                // Добавляем имя сегмента после '*'
                $resultSegments[] = $segments[$i];
            } else {
                // Обычный сегмент пути
                $resultSegments[] = $segments[$i];
            }
        }

        // Добавляем последний сегмент без *
        $resultSegments[] = $segments[count($segments) - 1];

        return ValidationConstants::CONTENT_JSON_PREFIX . implode('.', $resultSegments);
    }
}

