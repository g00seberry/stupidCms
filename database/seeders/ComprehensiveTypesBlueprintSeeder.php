<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Blueprint;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Database\Seeder;

/**
 * Seeder для создания комплексного Blueprint со всеми типами данных и кардинальностями.
 *
 * Создает blueprint 'comprehensive_types' с полным покрытием:
 * - Все типы данных (string, text, int, float, bool, datetime, json, ref)
 * - Все кардинальности (one, many)
 * - Многоуровневая вложенность (корень → nested_object → deep_object/deep_array)
 * - Всего 64 поля
 *
 * Структура:
 * 1. Корневой уровень: 16 полей (8 simple_* + 8 arr_*)
 * 2. nested_object (json, one): 16 полей + 2 вложенные структуры
 *    - deep_object (json, one): 16 полей
 *    - deep_array (json, many): 16 полей
 *
 * @package Database\Seeders
 */
class ComprehensiveTypesBlueprintSeeder extends Seeder
{
    /**
     * @param BlueprintStructureService $structureService
     */
    public function __construct(
        private readonly BlueprintStructureService $structureService
    ) {
    }

    /**
     * Все доступные типы данных.
     *
     * @var array<string>
     */
    private const DATA_TYPES = [
        'string',
        'text',
        'int',
        'float',
        'bool',
        'datetime',
        'json',
        'ref',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Основной blueprint со всеми типами данных
        $this->createComprehensiveTypes();

        // Дополнительные кейсы
        $this->createValidationComprehensive();
        $this->createIndexingComprehensive();
        $this->createDeepNesting();
        $this->createMixedCardinality();
        $this->createRealWorldExample();
    }

    /**
     * Создать основной blueprint со всеми типами данных.
     */
    private function createComprehensiveTypes(): void
    {
        $this->command->info('🔷 Creating comprehensive types blueprint...');

        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Comprehensive Types',
            'code' => 'comprehensive_types',
            'description' => 'Blueprint со всеми типами данных и кардинальностями на разных уровнях вложенности',
        ]);

        $sortOrder = 10;

        // 1. Корневой уровень: все типы с cardinality=one и cardinality=many
        $this->command->info('  → Creating root level fields...');
        $sortOrder = $this->createRootLevelFields($blueprint, $sortOrder);

        // 2. nested_object (json, one) - первый уровень вложенности
        $this->command->info('  → Creating nested_object structure...');
        $nestedObjectPath = $this->structureService->createPath($blueprint, [
            'name' => 'nested_object',
            'data_type' => 'json',
            'cardinality' => 'one',
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        // 2.1. Поля внутри nested_object: simple_* и arr_*
        $nestedSortOrder = 10;
        $nestedSortOrder = $this->createFieldsInObject($blueprint, $nestedObjectPath->id, $nestedSortOrder);

        // 2.2. deep_object (json, one) - второй уровень вложенности внутри nested_object
        $this->command->info('  → Creating deep_object structure inside nested_object...');
        $deepObjectPath = $this->structureService->createPath($blueprint, [
            'name' => 'deep_object',
            'parent_id' => $nestedObjectPath->id,
            'data_type' => 'json',
            'cardinality' => 'one',
            'sort_order' => $nestedSortOrder,
        ]);
        $deepSortOrder = 10;
        $this->createFieldsInObject($blueprint, $deepObjectPath->id, $deepSortOrder);

        // 2.3. deep_array (json, many) - массив внутри nested_object
        $this->command->info('  → Creating deep_array structure inside nested_object...');
        $deepArrayPath = $this->structureService->createPath($blueprint, [
            'name' => 'deep_array',
            'parent_id' => $nestedObjectPath->id,
            'data_type' => 'json',
            'cardinality' => 'many',
            'sort_order' => $nestedSortOrder + 10,
        ]);
        $deepArraySortOrder = 10;
        $this->createFieldsInObject($blueprint, $deepArrayPath->id, $deepArraySortOrder);

        $this->command->info('✅ Comprehensive types blueprint created successfully!');
        $this->printSummary($blueprint);
    }

    /**
     * Создать поля корневого уровня.
     *
     * Создает все типы данных с кардинальностями one и many.
     *
     * @param Blueprint $blueprint
     * @param int $startSortOrder
     * @return int Следующий sort_order
     */
    private function createRootLevelFields(Blueprint $blueprint, int $startSortOrder): int
    {
        $sortOrder = $startSortOrder;

        // Сначала все simple_* (cardinality=one)
        foreach (self::DATA_TYPES as $dataType) {
            $this->structureService->createPath($blueprint, [
                'name' => "simple_{$dataType}",
                'data_type' => $dataType,
                'cardinality' => 'one',
                'is_indexed' => $this->shouldIndexType($dataType),
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
        }

        // Затем все arr_* (cardinality=many)
        foreach (self::DATA_TYPES as $dataType) {
            $this->structureService->createPath($blueprint, [
                'name' => "arr_{$dataType}",
                'data_type' => $dataType,
                'cardinality' => 'many',
                'is_indexed' => $this->shouldIndexType($dataType),
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
        }

        return $sortOrder;
    }

    /**
     * Создать поля внутри объекта (nested_object, deep_object, deep_array).
     *
     * Создает все типы данных с кардинальностями one и many внутри указанного родителя.
     *
     * @param Blueprint $blueprint
     * @param int $parentId ID родительского path
     * @param int $startSortOrder
     * @return int Следующий sort_order
     */
    private function createFieldsInObject(Blueprint $blueprint, int $parentId, int $startSortOrder): int
    {
        $sortOrder = $startSortOrder;

        // Сначала все simple_* (cardinality=one)
        foreach (self::DATA_TYPES as $dataType) {
            $this->structureService->createPath($blueprint, [
                'name' => "simple_{$dataType}",
                'parent_id' => $parentId,
                'data_type' => $dataType,
                'cardinality' => 'one',
                'is_indexed' => $this->shouldIndexType($dataType),
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
        }

        // Затем все arr_* (cardinality=many)
        foreach (self::DATA_TYPES as $dataType) {
            $this->structureService->createPath($blueprint, [
                'name' => "arr_{$dataType}",
                'parent_id' => $parentId,
                'data_type' => $dataType,
                'cardinality' => 'many',
                'is_indexed' => $this->shouldIndexType($dataType),
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
        }

        return $sortOrder;
    }

    /**
     * Определить, нужно ли индексировать поле данного типа.
     *
     * Индексируем все типы кроме json (json используется только для структурирования).
     *
     * @param string $dataType
     * @return bool
     */
    private function shouldIndexType(string $dataType): bool
    {
        return $dataType !== 'json';
    }

    /**
     * Создать blueprint с различными правилами валидации.
     */
    private function createValidationComprehensive(): void
    {
        $this->command->info('🔷 Creating validation comprehensive blueprint...');

        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Validation Comprehensive',
            'code' => 'validation_comprehensive',
            'description' => 'Blueprint для тестирования всех правил валидации',
        ]);

        $sortOrder = 10;

        // Required правила
        $this->structureService->createPath($blueprint, [
            'name' => 'required_string',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'optional_string',
            'data_type' => 'string',
            'validation_rules' => ['required' => false],
            'is_indexed' => false,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        // Min/Max для строк
        $this->structureService->createPath($blueprint, [
            'name' => 'string_with_min_max',
            'data_type' => 'string',
            'validation_rules' => ['min' => 5, 'max' => 100],
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        // Min/Max для чисел
        $this->structureService->createPath($blueprint, [
            'name' => 'int_with_range',
            'data_type' => 'int',
            'validation_rules' => ['min' => 0, 'max' => 100],
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'float_with_range',
            'data_type' => 'float',
            'validation_rules' => ['min' => 0.0, 'max' => 999.99],
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        // Min/Max для массивов
        $this->structureService->createPath($blueprint, [
            'name' => 'arr_with_min_max',
            'data_type' => 'string',
            'cardinality' => 'many',
            'validation_rules' => ['min' => 1, 'max' => 10, 'distinct' => true],
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        // Pattern для строк
        $this->structureService->createPath($blueprint, [
            'name' => 'email_pattern',
            'data_type' => 'string',
            'validation_rules' => ['pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'],
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        // Условные правила
        $this->structureService->createPath($blueprint, [
            'name' => 'conditional_required',
            'data_type' => 'string',
            'validation_rules' => [
                'required_if' => ['field' => 'is_published', 'value' => true, 'operator' => '=='],
            ],
            'is_indexed' => false,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'is_published',
            'data_type' => 'bool',
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        // Field comparison
        $this->structureService->createPath($blueprint, [
            'name' => 'start_date',
            'data_type' => 'datetime',
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'end_date',
            'data_type' => 'datetime',
            'validation_rules' => [
                'field_comparison' => ['operator' => '>=', 'field' => 'start_date'],
            ],
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);

        $this->command->info('✅ Validation comprehensive blueprint created!');
        $this->printSummary($blueprint);
    }

    /**
     * Создать blueprint с различными вариантами индексации.
     */
    private function createIndexingComprehensive(): void
    {
        $this->command->info('🔷 Creating indexing comprehensive blueprint...');

        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Indexing Comprehensive',
            'code' => 'indexing_comprehensive',
            'description' => 'Blueprint для тестирования различных вариантов индексации',
        ]);

        $sortOrder = 10;

        // Индексированные поля корневого уровня
        foreach (['string', 'text', 'int', 'float', 'bool', 'datetime', 'ref'] as $type) {
            $this->structureService->createPath($blueprint, [
                'name' => "indexed_{$type}",
                'data_type' => $type,
                'is_indexed' => true,
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
        }

        // Неиндексированные поля
        foreach (['string', 'text', 'int', 'float', 'bool', 'datetime'] as $type) {
            $this->structureService->createPath($blueprint, [
                'name' => "non_indexed_{$type}",
                'data_type' => $type,
                'is_indexed' => false,
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
        }

        // Вложенный объект с индексированными полями
        $nestedPath = $this->structureService->createPath($blueprint, [
            'name' => 'nested_indexed',
            'data_type' => 'json',
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $nestedSortOrder = 10;
        foreach (['string', 'int', 'bool'] as $type) {
            $this->structureService->createPath($blueprint, [
                'name' => "indexed_{$type}",
                'parent_id' => $nestedPath->id,
                'data_type' => $type,
                'is_indexed' => true,
                'sort_order' => $nestedSortOrder,
            ]);
            $nestedSortOrder += 10;
        }

        // Массивы с индексацией
        foreach (['string', 'int', 'ref'] as $type) {
            $this->structureService->createPath($blueprint, [
                'name' => "indexed_arr_{$type}",
                'data_type' => $type,
                'cardinality' => 'many',
                'is_indexed' => true,
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
        }

        $this->command->info('✅ Indexing comprehensive blueprint created!');
        $this->printSummary($blueprint);
    }

    /**
     * Создать blueprint с глубокой вложенностью (5+ уровней).
     */
    private function createDeepNesting(): void
    {
        $this->command->info('🔷 Creating deep nesting blueprint...');

        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Deep Nesting',
            'code' => 'deep_nesting',
            'description' => 'Blueprint с максимальной глубиной вложенности (5+ уровней)',
        ]);

        $sortOrder = 10;

        // Создаем вложенность: level0 -> level1 -> level2 -> level3 -> level4 -> level5
        $level0 = $this->structureService->createPath($blueprint, [
            'name' => 'config',
            'data_type' => 'json',
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $level1 = $this->structureService->createPath($blueprint, [
            'name' => 'settings',
            'parent_id' => $level0->id,
            'data_type' => 'json',
            'sort_order' => 10,
        ]);

        $level2 = $this->structureService->createPath($blueprint, [
            'name' => 'ui',
            'parent_id' => $level1->id,
            'data_type' => 'json',
            'sort_order' => 10,
        ]);

        $level3 = $this->structureService->createPath($blueprint, [
            'name' => 'theme',
            'parent_id' => $level2->id,
            'data_type' => 'json',
            'sort_order' => 10,
        ]);

        $level4 = $this->structureService->createPath($blueprint, [
            'name' => 'colors',
            'parent_id' => $level3->id,
            'data_type' => 'json',
            'sort_order' => 10,
        ]);

        $level5 = $this->structureService->createPath($blueprint, [
            'name' => 'primary',
            'parent_id' => $level4->id,
            'data_type' => 'json',
            'sort_order' => 10,
        ]);

        // Добавляем поля на последнем уровне
        $this->structureService->createPath($blueprint, [
            'name' => 'hex',
            'parent_id' => $level5->id,
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $rgbPath = $this->structureService->createPath($blueprint, [
            'name' => 'rgb',
            'parent_id' => $level5->id,
            'data_type' => 'json',
            'sort_order' => 20,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'r',
            'parent_id' => $rgbPath->id,
            'data_type' => 'int',
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'g',
            'parent_id' => $rgbPath->id,
            'data_type' => 'int',
            'is_indexed' => true,
            'sort_order' => 20,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'b',
            'parent_id' => $rgbPath->id,
            'data_type' => 'int',
            'is_indexed' => true,
            'sort_order' => 30,
        ]);

        $this->command->info('✅ Deep nesting blueprint created!');
        $this->printSummary($blueprint);
    }

    /**
     * Создать blueprint со сложными комбинациями кардинальностей.
     */
    private function createMixedCardinality(): void
    {
        $this->command->info('🔷 Creating mixed cardinality blueprint...');

        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Mixed Cardinality',
            'code' => 'mixed_cardinality',
            'description' => 'Blueprint с сложными комбинациями кардинальностей',
        ]);

        $sortOrder = 10;

        // Массив объектов, каждый содержит массив примитивов
        $articlesPath = $this->structureService->createPath($blueprint, [
            'name' => 'articles',
            'data_type' => 'json',
            'cardinality' => 'many',
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $articleSortOrder = 10;
        $this->structureService->createPath($blueprint, [
            'name' => 'title',
            'parent_id' => $articlesPath->id,
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => $articleSortOrder,
        ]);
        $articleSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'tags',
            'parent_id' => $articlesPath->id,
            'data_type' => 'string',
            'cardinality' => 'many',
            'is_indexed' => true,
            'sort_order' => $articleSortOrder,
        ]);
        $articleSortOrder += 10;

        // Массив объектов, содержащих массив других объектов
        $authorsPath = $this->structureService->createPath($blueprint, [
            'name' => 'authors',
            'parent_id' => $articlesPath->id,
            'data_type' => 'json',
            'cardinality' => 'many',
            'sort_order' => $articleSortOrder,
        ]);
        $articleSortOrder += 10;

        $authorSortOrder = 10;
        $this->structureService->createPath($blueprint, [
            'name' => 'name',
            'parent_id' => $authorsPath->id,
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => $authorSortOrder,
        ]);
        $authorSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'contacts',
            'parent_id' => $authorsPath->id,
            'data_type' => 'string',
            'cardinality' => 'many',
            'is_indexed' => true,
            'sort_order' => $authorSortOrder,
        ]);

        // Объект с массивом объектов, содержащим массив примитивов
        $productPath = $this->structureService->createPath($blueprint, [
            'name' => 'product',
            'data_type' => 'json',
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $variationsPath = $this->structureService->createPath($blueprint, [
            'name' => 'variations',
            'parent_id' => $productPath->id,
            'data_type' => 'json',
            'cardinality' => 'many',
            'sort_order' => 10,
        ]);

        $variationSortOrder = 10;
        $this->structureService->createPath($blueprint, [
            'name' => 'size',
            'parent_id' => $variationsPath->id,
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => $variationSortOrder,
        ]);
        $variationSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'colors',
            'parent_id' => $variationsPath->id,
            'data_type' => 'string',
            'cardinality' => 'many',
            'is_indexed' => true,
            'sort_order' => $variationSortOrder,
        ]);

        $this->command->info('✅ Mixed cardinality blueprint created!');
        $this->printSummary($blueprint);
    }

    /**
     * Создать реалистичный пример blueprint (E-commerce Product).
     */
    private function createRealWorldExample(): void
    {
        $this->command->info('🔷 Creating real-world example blueprint (E-commerce Product)...');

        $blueprint = $this->structureService->createBlueprint([
            'name' => 'E-commerce Product',
            'code' => 'ecommerce_product',
            'description' => 'Реалистичный пример: структура товара для интернет-магазина',
        ]);

        $sortOrder = 10;

        // Базовая информация
        $this->structureService->createPath($blueprint, [
            'name' => 'title',
            'data_type' => 'string',
            'validation_rules' => ['required' => true, 'min' => 3, 'max' => 200],
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'description',
            'data_type' => 'text',
            'validation_rules' => ['required' => true],
            'is_indexed' => false,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'price',
            'data_type' => 'float',
            'validation_rules' => ['required' => true, 'min' => 0],
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'sku',
            'data_type' => 'string',
            'validation_rules' => ['required' => true, 'pattern' => '^[A-Z0-9-]+$'],
            'is_indexed' => true,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        // Вариации товара
        $variationsPath = $this->structureService->createPath($blueprint, [
            'name' => 'variations',
            'data_type' => 'json',
            'cardinality' => 'many',
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $variationSortOrder = 10;
        $this->structureService->createPath($blueprint, [
            'name' => 'size',
            'parent_id' => $variationsPath->id,
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => $variationSortOrder,
        ]);
        $variationSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'color',
            'parent_id' => $variationsPath->id,
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => $variationSortOrder,
        ]);
        $variationSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'price_modifier',
            'parent_id' => $variationsPath->id,
            'data_type' => 'float',
            'is_indexed' => false,
            'sort_order' => $variationSortOrder,
        ]);
        $variationSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'in_stock',
            'parent_id' => $variationsPath->id,
            'data_type' => 'bool',
            'is_indexed' => true,
            'sort_order' => $variationSortOrder,
        ]);

        // Медиа
        $this->structureService->createPath($blueprint, [
            'name' => 'images',
            'data_type' => 'string',
            'cardinality' => 'many',
            'is_indexed' => false,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'video_url',
            'data_type' => 'string',
            'validation_rules' => ['pattern' => '^https?://'],
            'is_indexed' => false,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        // SEO
        $seoPath = $this->structureService->createPath($blueprint, [
            'name' => 'seo',
            'data_type' => 'json',
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $seoSortOrder = 10;
        $this->structureService->createPath($blueprint, [
            'name' => 'meta_title',
            'parent_id' => $seoPath->id,
            'data_type' => 'string',
            'validation_rules' => ['max' => 60],
            'is_indexed' => false,
            'sort_order' => $seoSortOrder,
        ]);
        $seoSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'meta_description',
            'parent_id' => $seoPath->id,
            'data_type' => 'text',
            'validation_rules' => ['max' => 160],
            'is_indexed' => false,
            'sort_order' => $seoSortOrder,
        ]);
        $seoSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'keywords',
            'parent_id' => $seoPath->id,
            'data_type' => 'string',
            'cardinality' => 'many',
            'is_indexed' => false,
            'sort_order' => $seoSortOrder,
        ]);

        // Отзывы
        $reviewsPath = $this->structureService->createPath($blueprint, [
            'name' => 'reviews',
            'data_type' => 'json',
            'cardinality' => 'many',
            'sort_order' => $sortOrder,
        ]);
        $sortOrder += 10;

        $reviewSortOrder = 10;
        $this->structureService->createPath($blueprint, [
            'name' => 'author_name',
            'parent_id' => $reviewsPath->id,
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => false,
            'sort_order' => $reviewSortOrder,
        ]);
        $reviewSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'rating',
            'parent_id' => $reviewsPath->id,
            'data_type' => 'int',
            'validation_rules' => ['required' => true, 'min' => 1, 'max' => 5],
            'is_indexed' => true,
            'sort_order' => $reviewSortOrder,
        ]);
        $reviewSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'comment',
            'parent_id' => $reviewsPath->id,
            'data_type' => 'text',
            'is_indexed' => false,
            'sort_order' => $reviewSortOrder,
        ]);
        $reviewSortOrder += 10;

        $this->structureService->createPath($blueprint, [
            'name' => 'created_at',
            'parent_id' => $reviewsPath->id,
            'data_type' => 'datetime',
            'is_indexed' => true,
            'sort_order' => $reviewSortOrder,
        ]);

        $this->command->info('✅ Real-world example blueprint created!');
        $this->printSummary($blueprint);
    }

    /**
     * Вывести сводную статистику созданного blueprint.
     *
     * @param Blueprint $blueprint
     * @return void
     */
    private function printSummary(Blueprint $blueprint): void
    {
        $blueprint->refresh();
        $pathsCount = $blueprint->paths()->count();
        $rootPathsCount = $blueprint->paths()->whereNull('parent_id')->count();
        $nestedPathsCount = $blueprint->paths()->whereNotNull('parent_id')->count();
        $indexedPathsCount = $blueprint->paths()->where('is_indexed', true)->count();

        $this->command->newLine();
        $this->command->info('📊 Summary:');
        $this->command->info("  • Blueprint: '{$blueprint->code}' (ID: {$blueprint->id})");
        $this->command->info("  • Total Paths: {$pathsCount}");
        $this->command->info("    - Root level: {$rootPathsCount}");
        $this->command->info("    - Nested: {$nestedPathsCount}");
        $this->command->info("  • Indexed paths: {$indexedPathsCount}");
        $this->command->info("  • Data types covered: " . count(self::DATA_TYPES));
        $this->command->info("  • Cardinalities: one, many");
        $this->command->info("  • Nesting levels: 0, 1, 2");
    }
}

