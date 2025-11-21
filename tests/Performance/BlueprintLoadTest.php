<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use App\Services\Blueprint\BlueprintStructureService;
use App\Services\Blueprint\MaterializationService;
use App\Services\Blueprint\PathConflictValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Нагрузочные тесты системы Blueprints с реальными данными.
 *
 * Симулирует реальные сценарии использования:
 * - Создание сложных структур (статьи, продукты, пользователи)
 * - Материализация больших графов зависимостей
 * - Каскадная рематериализация при изменениях
 * - Проверка конфликтов на больших графах
 *
 * Измеряет:
 * - Время выполнения операций (мс)
 * - Количество SQL-запросов
 * - Использование памяти
 * - Производительность на разных масштабах данных
 *
 * @group performance
 * @group load
 */
class BlueprintLoadTest extends TestCase
{
    use RefreshDatabase;

    private BlueprintStructureService $structureService;
    private MaterializationService $materializationService;
    private PathConflictValidator $conflictValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->structureService = app(BlueprintStructureService::class);
        $this->materializationService = app(MaterializationService::class);
        $this->conflictValidator = app(PathConflictValidator::class);
    }

    /**
     * Тест: Материализация blueprint с большим количеством полей (реальный сценарий: статья блога).
     *
     * Симулирует:
     * - Статья с 50+ полями (заголовок, контент, метаданные, SEO, автор, категории, теги, медиа)
     * - Вложенные структуры (автор с контактами, медиа с метаданными)
     */
    public function test_materialization_large_article_blueprint(): void
    {
        // Создаём базовые blueprint'ы
        $authorBlueprint = $this->createAuthorBlueprint();
        $mediaBlueprint = $this->createMediaBlueprint();
        $seoBlueprint = $this->createSeoBlueprint();

        // Создаём основной blueprint статьи
        $articleBlueprint = $this->structureService->createBlueprint([
            'name' => 'Article',
            'code' => 'article',
        ]);

        // Добавляем основные поля статьи (50+ полей)
        $this->createArticleFields($articleBlueprint);

        // Встраиваем author, media, seo
        $authorPath = Path::where('blueprint_id', $articleBlueprint->id)
            ->where('name', 'author')
            ->first();
        $this->structureService->createEmbed($articleBlueprint, $authorBlueprint, $authorPath);

        $mediaPath = Path::where('blueprint_id', $articleBlueprint->id)
            ->where('name', 'media')
            ->first();
        $this->structureService->createEmbed($articleBlueprint, $mediaBlueprint, $mediaPath);

        $seoPath = Path::where('blueprint_id', $articleBlueprint->id)
            ->where('name', 'seo')
            ->first();
        $this->structureService->createEmbed($articleBlueprint, $seoBlueprint, $seoPath);

        // Создаём host blueprint для материализации
        $hostBlueprint = $this->structureService->createBlueprint([
            'name' => 'Host Article',
            'code' => 'host_article',
        ]);

        // Измеряем производительность материализации
        DB::enableQueryLog();
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $embed = BlueprintEmbed::where('blueprint_id', $hostBlueprint->id)
            ->where('embedded_blueprint_id', $articleBlueprint->id)
            ->first();

        if (!$embed) {
            $embed = BlueprintEmbed::create([
                'blueprint_id' => $hostBlueprint->id,
                'embedded_blueprint_id' => $articleBlueprint->id,
            ]);
        }

        $this->materializationService->materialize($embed);

        $duration = (microtime(true) - $startTime) * 1000; // мс
        $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024; // МБ
        $queries = count(DB::getQueryLog());

        // Проверяем результат
        $materializedPaths = Path::where('blueprint_id', $hostBlueprint->id)
            ->whereNotNull('blueprint_embed_id')
            ->count();

        $this->assertGreaterThan(50, $materializedPaths, 'Должно быть материализовано более 50 путей');

        // Выводим метрики
        dump([
            'test' => 'Материализация большой статьи',
            'duration_ms' => round($duration, 2),
            'queries' => $queries,
            'memory_mb' => round($memoryUsed, 2),
            'materialized_paths' => $materializedPaths,
            'queries_per_path' => round($queries / $materializedPaths, 2),
        ]);

        // Проверяем производительность
        $this->assertLessThan(2000, $duration, 'Материализация должна выполняться < 2 сек');
        // Примечание: 34 запроса включают проверку конфликтов для 3 встраиваний + материализацию
        // Без batch insert было бы ~66 запросов (по 1 на каждый путь)
        $this->assertLessThan(50, $queries, 'Должно быть < 50 запросов (включая проверки конфликтов)');
    }

    /**
     * Тест: Проверка конфликтов на большом графе (реальный сценарий: e-commerce каталог).
     *
     * Симулирует:
     * - Каталог продуктов с категориями, брендами, характеристиками
     * - Глубокую вложенность (продукт → категория → подкатегория → характеристики)
     * - Множественные встраивания одного blueprint
     */
    public function test_conflict_validation_large_ecommerce_graph(): void
    {
        // Создаём граф: Product → Category → Brand → Specifications
        $specsBlueprint = $this->createSpecificationsBlueprint();
        $brandBlueprint = $this->createBrandBlueprint();
        $categoryBlueprint = $this->createCategoryBlueprint($specsBlueprint);
        $productBlueprint = $this->createProductBlueprint($categoryBlueprint, $brandBlueprint);

        // Создаём host blueprint
        $hostBlueprint = $this->structureService->createBlueprint([
            'name' => 'Host Product',
            'code' => 'host_product',
        ]);

        // Измеряем проверку конфликтов
        DB::enableQueryLog();
        $startTime = microtime(true);

        $this->conflictValidator->validateNoConflicts(
            $productBlueprint,
            $hostBlueprint,
            null
        );

        $duration = (microtime(true) - $startTime) * 1000; // мс
        $queries = count(DB::getQueryLog());

        // Подсчитываем размер графа
        $totalBlueprints = 4; // product, category, brand, specs
        $totalPaths = Path::whereIn('blueprint_id', [
            $productBlueprint->id,
            $categoryBlueprint->id,
            $brandBlueprint->id,
            $specsBlueprint->id,
        ])
            ->whereNull('source_blueprint_id')
            ->count();

        dump([
            'test' => 'Проверка конфликтов e-commerce графа',
            'duration_ms' => round($duration, 2),
            'queries' => $queries,
            'total_blueprints' => $totalBlueprints,
            'total_paths' => $totalPaths,
            'avg_queries_per_blueprint' => round($queries / $totalBlueprints, 2),
        ]);

        // Проверяем производительность
        $this->assertLessThan(1000, $duration, 'Проверка конфликтов должна выполняться < 1 сек');
        $this->assertLessThan(15, $queries, 'Должно быть < 15 запросов благодаря eager loading');
    }

    /**
     * Тест: Каскадная рематериализация при изменении структуры (реальный сценарий: обновление схемы).
     *
     * Симулирует:
     * - Изменение базового blueprint (например, добавление поля в Author)
     * - Каскадную рематериализацию всех зависимых blueprint'ов
     * - Множественные встраивания одного blueprint
     */
    public function test_cascade_rematerialization_on_structure_change(): void
    {
        // Создаём базовый blueprint
        $authorBlueprint = $this->createAuthorBlueprint();

        // Создаём 5 blueprint'ов, которые встраивают author
        $dependentBlueprints = [];
        for ($i = 1; $i <= 5; $i++) {
            $bp = $this->structureService->createBlueprint([
                'name' => "Blueprint $i",
                'code' => "bp_$i",
            ]);

            $authorPath = Path::factory()->create([
                'blueprint_id' => $bp->id,
                'name' => 'author',
                'full_path' => 'author',
                'data_type' => 'json',
            ]);

            $this->structureService->createEmbed($bp, $authorBlueprint, $authorPath);
            $dependentBlueprints[] = $bp;
        }

        // Материализуем все встраивания
        foreach ($dependentBlueprints as $bp) {
            $embed = BlueprintEmbed::where('blueprint_id', $bp->id)
                ->where('embedded_blueprint_id', $authorBlueprint->id)
                ->first();
            $this->materializationService->materialize($embed);
        }

        // Измеряем каскадную рематериализацию при изменении author
        Event::fake([BlueprintStructureChanged::class]);
        DB::enableQueryLog();
        $startTime = microtime(true);

        // Добавляем новое поле в author
        $this->structureService->createPath($authorBlueprint, [
            'name' => 'social_links',
            'data_type' => 'json',
            'cardinality' => 'one',
        ]);

        // Триггерим событие вручную (так как Event::fake отключил реальные события)
        Event::assertDispatched(BlueprintStructureChanged::class, function ($event) use ($authorBlueprint) {
            return $event->blueprint->id === $authorBlueprint->id;
        });

        // Реальная рематериализация (без Event::fake)
        Event::fake([BlueprintStructureChanged::class]);
        $this->materializationService->rematerializeAllEmbeds($authorBlueprint);

        $duration = (microtime(true) - $startTime) * 1000; // мс
        $queries = count(DB::getQueryLog());

        dump([
            'test' => 'Каскадная рематериализация',
            'duration_ms' => round($duration, 2),
            'queries' => $queries,
            'dependent_blueprints' => count($dependentBlueprints),
            'avg_duration_per_blueprint' => round($duration / count($dependentBlueprints), 2),
        ]);

        // Проверяем производительность
        $this->assertLessThan(5000, $duration, 'Каскадная рематериализация должна выполняться < 5 сек');
    }

    /**
     * Тест: Материализация с очень большим количеством путей (стресс-тест).
     *
     * Симулирует:
     * - Blueprint с 500+ полями
     * - Глубокую вложенность (5 уровней)
     * - Множественные массивы (cardinality = many)
     */
    public function test_materialization_stress_test_500_paths(): void
    {
        $embeddedBlueprint = $this->structureService->createBlueprint([
            'name' => 'Large Blueprint',
            'code' => 'large',
        ]);

        // Создаём 500 полей с вложенностью
        $this->createLargeNestedStructure($embeddedBlueprint, 500, 5);

        $hostBlueprint = $this->structureService->createBlueprint([
            'name' => 'Host',
            'code' => 'host',
        ]);

        $embed = BlueprintEmbed::create([
            'blueprint_id' => $hostBlueprint->id,
            'embedded_blueprint_id' => $embeddedBlueprint->id,
        ]);

        // Измеряем производительность
        DB::enableQueryLog();
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->materializationService->materialize($embed);

        $duration = (microtime(true) - $startTime) * 1000; // мс
        $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024; // МБ
        $queries = count(DB::getQueryLog());

        $materializedPaths = Path::where('blueprint_id', $hostBlueprint->id)
            ->whereNotNull('blueprint_embed_id')
            ->count();

        dump([
            'test' => 'Стресс-тест: 500 путей',
            'duration_ms' => round($duration, 2),
            'queries' => $queries,
            'memory_mb' => round($memoryUsed, 2),
            'materialized_paths' => $materializedPaths,
            'paths_per_second' => round($materializedPaths / ($duration / 1000), 0),
        ]);

        // Проверяем производительность
        $this->assertGreaterThan(400, $materializedPaths, 'Должно быть материализовано ~500 путей');
        $this->assertLessThan(10000, $duration, 'Материализация 500 путей должна выполняться < 10 сек');
        $this->assertLessThan(30, $queries, 'Должно быть < 30 запросов благодаря batch insert');
    }

    /**
     * Тест: Проверка конфликтов с очень большим графом (стресс-тест).
     *
     * Симулирует:
     * - Граф из 50 blueprint'ов
     * - Каждый blueprint имеет 20 полей
     * - Глубина вложенности: 4 уровня
     */
    public function test_conflict_validation_stress_test_50_blueprints(): void
    {
        // Создаём граф из 50 blueprint'ов
        $blueprints = [];
        for ($i = 1; $i <= 50; $i++) {
            $bp = $this->structureService->createBlueprint([
                'name' => "Blueprint $i",
                'code' => "bp_$i",
            ]);

            // Каждый blueprint имеет 20 полей
            for ($j = 1; $j <= 20; $j++) {
                Path::factory()->create([
                    'blueprint_id' => $bp->id,
                    'name' => "field_$j",
                    'full_path' => "field_$j",
                ]);
            }

            $blueprints[] = $bp;
        }

        // Создаём граф зависимостей (цепочка + ветвления)
        $this->createComplexDependencyGraph($blueprints);

        $hostBlueprint = $this->structureService->createBlueprint([
            'name' => 'Host',
            'code' => 'host',
        ]);

        // Измеряем проверку конфликтов
        DB::enableQueryLog();
        $startTime = microtime(true);

        $this->conflictValidator->validateNoConflicts(
            $blueprints[0],
            $hostBlueprint,
            null
        );

        $duration = (microtime(true) - $startTime) * 1000; // мс
        $queries = count(DB::getQueryLog());

        $totalPaths = Path::whereIn('blueprint_id', collect($blueprints)->pluck('id')->all())
            ->whereNull('source_blueprint_id')
            ->count();

        dump([
            'test' => 'Стресс-тест: 50 blueprint\'ов',
            'duration_ms' => round($duration, 2),
            'queries' => $queries,
            'total_blueprints' => 50,
            'total_paths' => $totalPaths,
            'avg_queries_per_blueprint' => round($queries / 50, 2),
        ]);

        // Проверяем производительность
        $this->assertLessThan(5000, $duration, 'Проверка конфликтов должна выполняться < 5 сек');
        $this->assertLessThan(100, $queries, 'Должно быть < 100 запросов благодаря батчингу');
    }

    // ============================================
    // Вспомогательные методы для создания данных
    // ============================================

    /**
     * Создать blueprint автора (реальные поля).
     */
    private function createAuthorBlueprint(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Author',
            'code' => 'author',
        ]);

        $fields = [
            ['name' => 'name', 'data_type' => 'string', 'is_required' => true],
            ['name' => 'email', 'data_type' => 'string', 'is_required' => true],
            ['name' => 'bio', 'data_type' => 'text'],
            ['name' => 'avatar', 'data_type' => 'string'],
            ['name' => 'contacts', 'data_type' => 'json', 'cardinality' => 'one'],
        ];

        foreach ($fields as $field) {
            $this->structureService->createPath($blueprint, $field);
        }

        // Вложенные поля в contacts
        $contactsPath = Path::where('blueprint_id', $blueprint->id)
            ->where('name', 'contacts')
            ->first();

        $nestedFields = [
            ['name' => 'phone', 'data_type' => 'string', 'parent_id' => $contactsPath->id],
            ['name' => 'website', 'data_type' => 'string', 'parent_id' => $contactsPath->id],
            ['name' => 'social', 'data_type' => 'json', 'parent_id' => $contactsPath->id],
        ];

        foreach ($nestedFields as $field) {
            $this->structureService->createPath($blueprint, $field);
        }

        return $blueprint;
    }

    /**
     * Создать blueprint медиа (реальные поля).
     */
    private function createMediaBlueprint(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Media',
            'code' => 'media',
        ]);

        $fields = [
            ['name' => 'url', 'data_type' => 'string', 'is_required' => true],
            ['name' => 'alt', 'data_type' => 'string'],
            ['name' => 'caption', 'data_type' => 'text'],
            ['name' => 'width', 'data_type' => 'int'],
            ['name' => 'height', 'data_type' => 'int'],
            ['name' => 'metadata', 'data_type' => 'json'],
        ];

        foreach ($fields as $field) {
            $this->structureService->createPath($blueprint, $field);
        }

        return $blueprint;
    }

    /**
     * Создать blueprint SEO (реальные поля).
     */
    private function createSeoBlueprint(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'SEO',
            'code' => 'seo',
        ]);

        $fields = [
            ['name' => 'title', 'data_type' => 'string'],
            ['name' => 'description', 'data_type' => 'text'],
            ['name' => 'keywords', 'data_type' => 'string'],
            ['name' => 'og_image', 'data_type' => 'string'],
            ['name' => 'og_title', 'data_type' => 'string'],
            ['name' => 'og_description', 'data_type' => 'text'],
        ];

        foreach ($fields as $field) {
            $this->structureService->createPath($blueprint, $field);
        }

        return $blueprint;
    }

    /**
     * Создать поля статьи (50+ полей).
     */
    private function createArticleFields(Blueprint $blueprint): void
    {
        $fields = [
            // Основные поля
            ['name' => 'title', 'data_type' => 'string', 'is_required' => true, 'is_indexed' => true],
            ['name' => 'slug', 'data_type' => 'string', 'is_required' => true],
            ['name' => 'excerpt', 'data_type' => 'text'],
            ['name' => 'content', 'data_type' => 'text', 'is_required' => true],
            ['name' => 'published_at', 'data_type' => 'datetime'],
            ['name' => 'status', 'data_type' => 'string'],

            // Вложенные структуры
            ['name' => 'author', 'data_type' => 'json', 'cardinality' => 'one'],
            ['name' => 'media', 'data_type' => 'json', 'cardinality' => 'many'],
            ['name' => 'seo', 'data_type' => 'json', 'cardinality' => 'one'],

            // Метаданные
            ['name' => 'meta', 'data_type' => 'json'],
        ];

        foreach ($fields as $field) {
            $this->structureService->createPath($blueprint, $field);
        }

        // Вложенные поля в meta
        $metaPath = Path::where('blueprint_id', $blueprint->id)
            ->where('name', 'meta')
            ->first();

        $metaFields = [
            ['name' => 'views', 'data_type' => 'int', 'parent_id' => $metaPath->id],
            ['name' => 'likes', 'data_type' => 'int', 'parent_id' => $metaPath->id],
            ['name' => 'shares', 'data_type' => 'int', 'parent_id' => $metaPath->id],
            ['name' => 'reading_time', 'data_type' => 'int', 'parent_id' => $metaPath->id],
            ['name' => 'tags', 'data_type' => 'string', 'parent_id' => $metaPath->id, 'cardinality' => 'many'],
            ['name' => 'categories', 'data_type' => 'string', 'parent_id' => $metaPath->id, 'cardinality' => 'many'],
        ];

        foreach ($metaFields as $field) {
            $this->structureService->createPath($blueprint, $field);
        }

        // Добавляем ещё поля для достижения 50+
        for ($i = 1; $i <= 30; $i++) {
            $this->structureService->createPath($blueprint, [
                'name' => "custom_field_$i",
                'data_type' => 'string',
            ]);
        }
    }

    /**
     * Создать blueprint характеристик продукта.
     */
    private function createSpecificationsBlueprint(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Specifications',
            'code' => 'specifications',
        ]);

        $fields = [
            ['name' => 'weight', 'data_type' => 'float'],
            ['name' => 'dimensions', 'data_type' => 'json'],
            ['name' => 'color', 'data_type' => 'string'],
            ['name' => 'material', 'data_type' => 'string'],
            ['name' => 'warranty', 'data_type' => 'string'],
        ];

        foreach ($fields as $field) {
            $this->structureService->createPath($blueprint, $field);
        }

        return $blueprint;
    }

    /**
     * Создать blueprint бренда.
     */
    private function createBrandBlueprint(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Brand',
            'code' => 'brand',
        ]);

        $fields = [
            ['name' => 'name', 'data_type' => 'string', 'is_required' => true],
            ['name' => 'logo', 'data_type' => 'string'],
            ['name' => 'website', 'data_type' => 'string'],
            ['name' => 'description', 'data_type' => 'text'],
        ];

        foreach ($fields as $field) {
            $this->structureService->createPath($blueprint, $field);
        }

        return $blueprint;
    }

    /**
     * Создать blueprint категории с встраиванием specifications.
     */
    private function createCategoryBlueprint(Blueprint $specsBlueprint): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Category',
            'code' => 'category',
        ]);

        $fields = [
            ['name' => 'name', 'data_type' => 'string', 'is_required' => true],
            ['name' => 'slug', 'data_type' => 'string'],
            ['name' => 'description', 'data_type' => 'text'],
            ['name' => 'specifications', 'data_type' => 'json'],
        ];

        foreach ($fields as $field) {
            $this->structureService->createPath($blueprint, $field);
        }

        $specsPath = Path::where('blueprint_id', $blueprint->id)
            ->where('name', 'specifications')
            ->first();

        $this->structureService->createEmbed($blueprint, $specsBlueprint, $specsPath);

        return $blueprint;
    }

    /**
     * Создать blueprint продукта с встраиванием category и brand.
     */
    private function createProductBlueprint(Blueprint $categoryBlueprint, Blueprint $brandBlueprint): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Product',
            'code' => 'product',
        ]);

        $fields = [
            ['name' => 'name', 'data_type' => 'string', 'is_required' => true],
            ['name' => 'sku', 'data_type' => 'string', 'is_required' => true],
            ['name' => 'price', 'data_type' => 'float', 'is_required' => true],
            ['name' => 'description', 'data_type' => 'text'],
            ['name' => 'category', 'data_type' => 'json'],
            ['name' => 'brand', 'data_type' => 'json'],
        ];

        foreach ($fields as $field) {
            $this->structureService->createPath($blueprint, $field);
        }

        $categoryPath = Path::where('blueprint_id', $blueprint->id)
            ->where('name', 'category')
            ->first();
        $this->structureService->createEmbed($blueprint, $categoryBlueprint, $categoryPath);

        $brandPath = Path::where('blueprint_id', $blueprint->id)
            ->where('name', 'brand')
            ->first();
        $this->structureService->createEmbed($blueprint, $brandBlueprint, $brandPath);

        return $blueprint;
    }

    /**
     * Создать большую вложенную структуру.
     */
    private function createLargeNestedStructure(Blueprint $blueprint, int $totalPaths, int $maxDepth): void
    {
        $pathsPerLevel = (int) ceil($totalPaths / $maxDepth);
        $created = 0;

        for ($level = 0; $level < $maxDepth && $created < $totalPaths; $level++) {
            $parentPaths = $level === 0
                ? [null]
                : Path::where('blueprint_id', $blueprint->id)
                    ->where('full_path', 'like', str_repeat('level%', $level))
                    ->get()
                    ->take($pathsPerLevel);

            foreach ($parentPaths as $parent) {
                for ($i = 0; $i < $pathsPerLevel && $created < $totalPaths; $i++) {
                    $this->structureService->createPath($blueprint, [
                        'name' => "level{$level}_field_$i",
                        'data_type' => $level < $maxDepth - 1 ? 'json' : 'string',
                        'parent_id' => $parent?->id,
                    ]);
                    $created++;
                }
            }
        }
    }

    /**
     * Создать сложный граф зависимостей.
     */
    private function createComplexDependencyGraph(array $blueprints): void
    {
        // Создаём цепочку: bp1 → bp2 → ... → bp10
        for ($i = 0; $i < 9; $i++) {
            $groupPath = Path::factory()->create([
                'blueprint_id' => $blueprints[$i]->id,
                'name' => 'next',
                'full_path' => 'next',
                'data_type' => 'json',
            ]);

            BlueprintEmbed::create([
                'blueprint_id' => $blueprints[$i]->id,
                'embedded_blueprint_id' => $blueprints[$i + 1]->id,
                'host_path_id' => $groupPath->id,
            ]);
        }

        // Создаём ветвления: bp10 встраивает bp11-20
        $bp10 = $blueprints[9];
        for ($i = 10; $i < 20 && $i < count($blueprints); $i++) {
            $groupPath = Path::factory()->create([
                'blueprint_id' => $bp10->id,
                'name' => "branch_$i",
                'full_path' => "branch_$i",
                'data_type' => 'json',
            ]);

            BlueprintEmbed::create([
                'blueprint_id' => $bp10->id,
                'embedded_blueprint_id' => $blueprints[$i]->id,
                'host_path_id' => $groupPath->id,
            ]);
        }
    }
}

