<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Support\Facades\Cache;

/**
 * Валидатор контента Entry на основе Blueprint.
 *
 * Строит правила валидации Laravel для поля content_json на основе
 * структуры Path в Blueprint. Преобразует full_path в точечную нотацию
 * и применяет validation_rules из каждого Path.
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
     * Построить правила валидации для content_json на основе Path blueprint.
     *
     * Загружает все Path из blueprint и преобразует их в правила валидации Laravel.
     * Использует кэширование для оптимизации производительности.
     * Учитывает:
     * - full_path → точечная нотация для Laravel (content_json.title, content_json.author.name)
     * - is_required → required или nullable
     * - cardinality: 'many' → правила для массива и для элементов (.*)
     * - validation_rules → преобразуются через PathValidationRulesConverter
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для валидации
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     *         Массив правил валидации, где ключи - это пути в точечной нотации
     */
    public function buildRules(Blueprint $blueprint): array
    {
        $cacheKey = "blueprint:validation_rules:{$blueprint->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($blueprint): array {
            $rules = [];

            // Загружаем все Path из blueprint (включая скопированные)
            $paths = $blueprint->paths()
                ->select(['id', 'name', 'full_path', 'data_type', 'cardinality', 'is_required', 'validation_rules'])
                ->orderByRaw('LENGTH(full_path), full_path')
                ->get();

            if ($paths->isEmpty()) {
                return $rules;
            }

            // Обрабатываем каждый Path
            foreach ($paths as $path) {
                $fieldPath = $this->buildFieldPath($path->full_path);

                // Для cardinality: 'many' создаём правила для массива и для элементов
                if ($path->cardinality === 'many') {
                    // Правило для самого массива
                    $arrayRules = $this->buildArrayRules($path->is_required);
                    $rules[$fieldPath] = $arrayRules;

                    // Правило для элементов массива
                    $elementRules = PathValidationRulesConverter::convertLegacy(
                        $path->validation_rules,
                        $path->data_type,
                        false, // Элементы массива не могут быть required
                        'one' // Элементы обрабатываются как одиночные значения
                    );
                    // Удаляем nullable из правил элементов массива (элементы массива не могут быть nullable)
                    $elementRules = array_filter($elementRules, fn ($rule) => $rule !== 'nullable');
                    $rules[$fieldPath.'.*'] = array_values($elementRules);
                } else {
                    // Для cardinality: 'one' создаём правило для самого поля
                    $fieldRules = PathValidationRulesConverter::convertLegacy(
                        $path->validation_rules,
                        $path->data_type,
                        $path->is_required,
                        'one'
                    );
                    $rules[$fieldPath] = $fieldRules;
                }
            }

            return $rules;
        });
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
     * Построить путь поля в точечной нотации для Laravel валидации.
     *
     * Преобразует full_path из Path в путь для content_json.
     * Например: 'title' → 'content_json.title', 'author.name' → 'content_json.author.name'
     *
     * @param string $fullPath Полный путь из Path (например, 'author.contacts.phone')
     * @return string Путь в точечной нотации для Laravel (например, 'content_json.author.contacts.phone')
     */
    private function buildFieldPath(string $fullPath): string
    {
        return 'content_json.'.$fullPath;
    }

    /**
     * Построить правила валидации для массива (cardinality: 'many').
     *
     * Создаёт правила для самого массива: required/nullable и array.
     *
     * @param bool $isRequired Обязательное ли поле
     * @return array<int, string> Правила валидации для массива
     */
    private function buildArrayRules(bool $isRequired): array
    {
        $rules = ['array'];

        if ($isRequired) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }
}

