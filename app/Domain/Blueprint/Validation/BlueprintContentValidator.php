<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface;
use App\Domain\Blueprint\Validation\DataTypeMapper;
use App\Domain\Blueprint\Validation\EntryValidationServiceInterface;
use App\Domain\Blueprint\Validation\FieldPathBuilder;
use App\Domain\Blueprint\Validation\RuleArrayManipulator;
use App\Domain\Blueprint\Validation\ValidationConstants;
use App\Models\Blueprint;
use Illuminate\Support\Facades\Cache;

/**
 * Валидатор контента Entry на основе Blueprint.
 *
 * Строит правила валидации Laravel для поля content_json на основе
 * структуры Path в Blueprint. Использует EntryValidationService для построения
 * доменных правил и LaravelValidationAdapter для преобразования в Laravel правила.
 * Использует кэширование для оптимизации производительности.
 *
 * @package App\Domain\Blueprint\Validation
 */
final class BlueprintContentValidator implements BlueprintContentValidatorInterface
{
    /**
     * TTL для кэша правил валидации (в секундах).
     *
     * @var int
     */
    private const CACHE_TTL = 3600; // 1 час

    /**
     * @param \App\Domain\Blueprint\Validation\EntryValidationServiceInterface $validationService Сервис для построения правил валидации
     * @param \App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface $adapter Адаптер для преобразования в Laravel правила
     * @param \App\Domain\Blueprint\Validation\DataTypeMapper $dataTypeMapper Маппер типов данных
     * @param \App\Domain\Blueprint\Validation\FieldPathBuilder $fieldPathBuilder Построитель путей полей
     * @param \App\Domain\Blueprint\Validation\RuleArrayManipulator $ruleArrayManipulator Манипулятор массивов правил
     */
    public function __construct(
        private readonly EntryValidationServiceInterface $validationService,
        private readonly LaravelValidationAdapterInterface $adapter,
        private readonly DataTypeMapper $dataTypeMapper,
        private readonly FieldPathBuilder $fieldPathBuilder,
        private readonly RuleArrayManipulator $ruleArrayManipulator
    ) {}

    /**
     * Построить правила валидации для content_json на основе Path blueprint.
     *
     * Использует EntryValidationService для построения доменных правил
     * и LaravelValidationAdapter для преобразования в Laravel правила.
     * Использует кэширование для оптимизации производительности.
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для валидации
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     *         Массив правил валидации, где ключи - это пути в точечной нотации
     */
    public function buildRules(Blueprint $blueprint): array
    {
        $cacheKey = "blueprint:validation_rules:{$blueprint->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($blueprint): array {
            // Используем EntryValidationService для построения доменных правил
            $ruleSet = $this->validationService->buildRulesFor($blueprint);

            // Собираем dataTypes и cardinality для адаптера
            [$dataTypes, $cardinalities] = $this->collectPathMetadata($blueprint);

            // Преобразуем доменные правила в Laravel правила через адаптер
            $rules = $this->adapter->adapt($ruleSet, $dataTypes);

            // Добавляем правило 'array' для полей с cardinality: 'many'
            // И базовый тип для элементов массива, если его нет
            foreach ($cardinalities as $fieldPath => $cardinality) {
                if ($cardinality === ValidationConstants::CARDINALITY_MANY) {
                    // Добавляем 'array' для самого массива
                    if (isset($rules[$fieldPath])) {
                        $this->ruleArrayManipulator->ensureArrayRule($rules[$fieldPath]);
                    }

                    // Добавляем базовый тип для элементов массива, если правил нет
                    $elementPath = $fieldPath.ValidationConstants::ARRAY_ELEMENT_WILDCARD;
                    if (! isset($rules[$elementPath]) && isset($dataTypes[$elementPath])) {
                        $baseType = $this->dataTypeMapper->toLaravelRule($dataTypes[$elementPath]);
                        if ($baseType !== null) {
                            $rules[$elementPath] = [$baseType];
                        }
                    }
                }
            }

            return $rules;
        });
    }

    /**
     * Собрать метаданные путей (dataTypes и cardinality) для адаптера.
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для сбора метаданных
     * @return array{0: array<string, string>, 1: array<string, string>} Массив из двух элементов: [dataTypes, cardinalities]
     */
    private function collectPathMetadata(Blueprint $blueprint): array
    {
        $dataTypes = [];
        $cardinalities = [];

        $paths = $blueprint->paths()
            ->select(['full_path', 'data_type', 'cardinality'])
            ->get();

        // Создаём маппинг full_path → cardinality для определения родительских массивов
        $pathCardinalities = [];
        foreach ($paths as $path) {
            $pathCardinalities[$path->full_path] = $path->cardinality;
        }

        foreach ($paths as $path) {
            $fieldPath = $this->fieldPathBuilder->buildFieldPath($path->full_path, $pathCardinalities);
            $dataTypes[$fieldPath] = $path->data_type;
            $cardinalities[$fieldPath] = $path->cardinality;

            // Для полей с cardinality: 'many' добавляем dataType для элементов массива
            if ($path->cardinality === ValidationConstants::CARDINALITY_MANY) {
                $elementPath = $fieldPath.ValidationConstants::ARRAY_ELEMENT_WILDCARD;
                $dataTypes[$elementPath] = $path->data_type;
            }
        }

        return [$dataTypes, $cardinalities];
    }


    /**
     * Инвалидировать кэш правил валидации для blueprint.
     *
     * Вызывается при изменении структуры Path в blueprint.
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для инвалидации кэша
     * @return void
     */
    public function invalidateCache(Blueprint $blueprint): void
    {
        $cacheKey = "blueprint:validation_rules:{$blueprint->id}";
        Cache::forget($cacheKey);
    }

}

