<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface;
use App\Domain\Blueprint\Validation\EntryValidationServiceInterface;
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
     */
    public function __construct(
        private readonly EntryValidationServiceInterface $validationService,
        private readonly LaravelValidationAdapterInterface $adapter
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
                if ($cardinality === 'many') {
                    // Добавляем 'array' для самого массива
                    if (isset($rules[$fieldPath])) {
                        $this->insertArrayRule($rules[$fieldPath]);
                    }

                    // Добавляем базовый тип для элементов массива, если правил нет
                    $elementPath = $fieldPath.'.*';
                    if (! isset($rules[$elementPath]) && isset($dataTypes[$elementPath])) {
                        $baseType = $this->getBaseTypeForDataType($dataTypes[$elementPath]);
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

        foreach ($paths as $path) {
            $fieldPath = 'content_json.'.$path->full_path;
            $dataTypes[$fieldPath] = $path->data_type;
            $cardinalities[$fieldPath] = $path->cardinality;

            // Для полей с cardinality: 'many' добавляем dataType для элементов массива
            if ($path->cardinality === 'many') {
                $elementPath = $fieldPath.'.*';
                $dataTypes[$elementPath] = $path->data_type;
            }
        }

        return [$dataTypes, $cardinalities];
    }

    /**
     * Вставить правило 'array' в массив правил после required/nullable.
     *
     * @param array<int, string> $rules Массив правил (изменяется по ссылке)
     * @return void
     */
    private function insertArrayRule(array &$rules): void
    {
        // Ищем позицию после required/nullable
        $insertPosition = 0;
        foreach ($rules as $index => $rule) {
            if (in_array($rule, ['required', 'nullable'], true)) {
                $insertPosition = $index + 1;
            } else {
                break;
            }
        }

        // Вставляем 'array' только если его ещё нет
        if (! in_array('array', $rules, true)) {
            array_splice($rules, $insertPosition, 0, ['array']);
        }
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

    /**
     * Получить базовый тип валидации Laravel по data_type Path.
     *
     * @param string $dataType Тип данных Path
     * @return string|null Правило валидации Laravel или null
     */
    private function getBaseTypeForDataType(string $dataType): ?string
    {
        return match ($dataType) {
            'string', 'text' => 'string',
            'int' => 'integer',
            'float' => 'numeric',
            'bool' => 'boolean',
            'date' => 'date',
            'datetime' => 'date',
            'json' => 'array',
            'ref' => 'integer',
            default => null,
        };
    }
}

