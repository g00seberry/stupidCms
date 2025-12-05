<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use App\Services\Blueprint\MaterializationService;
use App\Services\Blueprint\PathConflictValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Профилирующие тесты для оптимизации работы с большими графами blueprint'ов.
 *
 * Измеряет:
 * - Количество SQL-запросов при материализации
 * - Время выполнения операций
 * - Эффективность кеширования графа зависимостей
 *
 * @group performance
 */
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->materializationService = app(MaterializationService::class);
    $this->conflictValidator = app(PathConflictValidator::class);
});

test('большой граф: материализация десятков embeds', function () {
    // Создаём 30 blueprint'ов с полями
    $blueprints = collect(range(1, 30))->map(function ($i) {
        $bp = Blueprint::factory()->create(['code' => "bp$i"]);
        
        // Каждый blueprint имеет 5 полей
        for ($j = 1; $j <= 5; $j++) {
            Path::factory()->create([
                'blueprint_id' => $bp->id,
                'name' => "field{$i}_{$j}",
                'full_path' => "field{$i}_{$j}",
            ]);
        }
        
        return $bp;
    });

    // Создаём граф зависимостей: bp1 встраивает bp2-10, bp2 встраивает bp11-20, и т.д.
    $rootBlueprint = $blueprints[0]; // bp1
    $embeds = [];

    // bp1 встраивает bp2-10 (9 embeds)
    for ($i = 2; $i <= 10; $i++) {
        $groupPath = Path::factory()->create([
            'blueprint_id' => $rootBlueprint->id,
            'name' => "group$i",
            'full_path' => "group$i",
        ]);

        $embeds[] = BlueprintEmbed::create([
            'blueprint_id' => $rootBlueprint->id,
            'embedded_blueprint_id' => $blueprints[$i - 1]->id,
            'host_path_id' => $groupPath->id,
        ]);
    }

    // bp2 встраивает bp11-15 (5 embeds)
    $bp2 = $blueprints[1];
    for ($i = 11; $i <= 15; $i++) {
        $groupPath = Path::factory()->create([
            'blueprint_id' => $bp2->id,
            'name' => "subgroup$i",
            'full_path' => "subgroup$i",
        ]);

        $embeds[] = BlueprintEmbed::create([
            'blueprint_id' => $bp2->id,
            'embedded_blueprint_id' => $blueprints[$i - 1]->id,
            'host_path_id' => $groupPath->id,
        ]);
    }

    // Измеряем количество запросов и время для проверки конфликтов
    DB::enableQueryLog();
    $startTime = microtime(true);

    // Проверка конфликтов должна загрузить весь граф одним набором запросов
    $this->conflictValidator->validateNoConflicts(
        $rootBlueprint,
        Blueprint::factory()->create(['code' => 'host']),
        null
    );

    $validationTime = microtime(true) - $startTime;
    $validationQueries = count(DB::getQueryLog());

    // Материализуем первый embed
    DB::flushQueryLog();
    $startTime = microtime(true);

    $this->materializationService->materialize($embeds[0]);

    $materializationTime = microtime(true) - $startTime;
    $materializationQueries = count(DB::getQueryLog());

    // Проверяем, что созданы все необходимые копии
    $copiesCount = Path::where('blueprint_embed_id', $embeds[0]->id)->count();
    expect($copiesCount)->toBeGreaterThan(0);

    // Профилирующие метрики
    dump([
        'validation_time_ms' => round($validationTime * 1000, 2),
        'validation_queries' => $validationQueries,
        'materialization_time_ms' => round($materializationTime * 1000, 2),
        'materialization_queries' => $materializationQueries,
        'copies_created' => $copiesCount,
    ]);

    // Проверяем разумные лимиты производительности
    // Проверка конфликтов должна быть быстрой благодаря кешированию
    expect($validationQueries)->toBeLessThan(50) // Не более 50 запросов для графа с 30 blueprint'ами
        ->and($validationTime)->toBeLessThan(2.0); // Не более 2 секунд
})->group('performance');

test('большой граф: измерение производительности с eager loading', function () {
    // Создаём цепочку из 20 blueprint'ов
    $blueprints = collect(range(1, 20))->map(function ($i) {
        $bp = Blueprint::factory()->create(['code' => "chain$i"]);
        
        Path::factory()->create([
            'blueprint_id' => $bp->id,
            'name' => "field$i",
            'full_path' => "field$i",
        ]);
        
        return $bp;
    });

    // Создаём цепочку встраиваний: chain1 → chain2 → ... → chain20
    $embeds = [];
    for ($i = 0; $i < 19; $i++) {
        $groupPath = Path::factory()->create([
            'blueprint_id' => $blueprints[$i]->id,
            'name' => "next",
            'full_path' => "next",
        ]);

        $embeds[] = BlueprintEmbed::create([
            'blueprint_id' => $blueprints[$i]->id,
            'embedded_blueprint_id' => $blueprints[$i + 1]->id,
            'host_path_id' => $groupPath->id,
        ]);
    }

    $host = Blueprint::factory()->create(['code' => 'host']);

    // Измеряем проверку конфликтов для всей цепочки
    DB::enableQueryLog();
    $startTime = microtime(true);

    $this->conflictValidator->validateNoConflicts(
        $blueprints[0],
        $host,
        null
    );

    $time = microtime(true) - $startTime;
    $queries = count(DB::getQueryLog());

    dump([
        'chain_length' => 20,
        'validation_time_ms' => round($time * 1000, 2),
        'total_queries' => $queries,
        'queries_per_blueprint' => round($queries / 20, 2),
    ]);

    // С оптимизацией количество запросов должно быть линейным или лучше
    // Ранее (без оптимизации) было бы ~20+ запросов на blueprint (рекурсивные)
    // С оптимизацией должно быть ~30-40 запросов (BFS + батчинг проверки конфликтов)
    expect($queries)->toBeLessThan(50) // Приемлемое количество запросов с батчингом
        ->and($time)->toBeLessThan(1.0); // Быстрее 1 секунды
})->group('performance');

test('большой граф: сравнение до/после оптимизации', function () {
    // Создаём сложный граф: один blueprint встраивает 15 других, каждый из которых встраивает ещё 2
    // Всего: 1 + 15 + 30 = 46 blueprint'ов
    $root = Blueprint::factory()->create(['code' => 'root']);
    
    // Уровень 1: 15 blueprint'ов
    $level1 = collect(range(1, 15))->map(function ($i) {
        $bp = Blueprint::factory()->create(['code' => "l1_$i"]);
        
        for ($j = 1; $j <= 3; $j++) {
            Path::factory()->create([
                'blueprint_id' => $bp->id,
                'name' => "f{$i}_{$j}",
                'full_path' => "f{$i}_{$j}",
            ]);
        }
        
        return $bp;
    });

    // Уровень 2: 30 blueprint'ов (по 2 на каждый level1)
    $level2 = collect(range(1, 30))->map(function ($i) {
        $bp = Blueprint::factory()->create(['code' => "l2_$i"]);
        
        Path::factory()->create([
            'blueprint_id' => $bp->id,
            'name' => "field$i",
            'full_path' => "field$i",
        ]);
        
        return $bp;
    });

    // Создаём embeds
    // root → level1 (15 embeds)
    $rootEmbeds = [];
    foreach ($level1->take(15) as $i => $bp) {
        $groupPath = Path::factory()->create([
            'blueprint_id' => $root->id,
            'name' => "group$i",
            'full_path' => "group$i",
        ]);

        $rootEmbeds[] = BlueprintEmbed::create([
            'blueprint_id' => $root->id,
            'embedded_blueprint_id' => $bp->id,
            'host_path_id' => $groupPath->id,
        ]);
    }

    // level1 → level2 (30 embeds, по 2 на каждый level1)
    $level1Embeds = [];
    foreach ($level1 as $i => $bp) {
        for ($j = 0; $j < 2; $j++) {
            $groupPath = Path::factory()->create([
                'blueprint_id' => $bp->id,
                'name' => "sub{$j}",
                'full_path' => "sub{$j}",
            ]);

            $level1Embeds[] = BlueprintEmbed::create([
                'blueprint_id' => $bp->id,
                'embedded_blueprint_id' => $level2[$i * 2 + $j]->id,
                'host_path_id' => $groupPath->id,
            ]);
        }
    }

    $host = Blueprint::factory()->create(['code' => 'host']);

    // Измеряем проверку конфликтов
    DB::enableQueryLog();
    $startTime = microtime(true);

    $this->conflictValidator->validateNoConflicts($root, $host, null);

    $time = microtime(true) - $startTime;
    $queries = count(DB::getQueryLog());

    $totalBlueprints = 1 + 15 + 30; // 46

    dump([
        'total_blueprints' => $totalBlueprints,
        'total_paths' => Path::whereIn('blueprint_id', collect([$root->id])
            ->merge($level1->pluck('id'))
            ->merge($level2->pluck('id'))
            ->all())
            ->whereNull('source_blueprint_id')
            ->count(),
        'total_embeds' => 15 + 30, // 45
        'validation_time_ms' => round($time * 1000, 2),
        'total_queries' => $queries,
        'avg_queries_per_blueprint' => round($queries / $totalBlueprints, 2),
    ]);

    // Проверяем, что оптимизация работает
    // Без оптимизации было бы ~46 * 3 = 138+ запросов (рекурсивные запросы для каждого blueprint)
    // С оптимизацией должно быть ~80-100 запросов (BFS + батчинг проверки конфликтов)
    expect($queries)->toBeLessThan(120) // Значительно меньше чем без оптимизации (было бы 138+)
        ->and($time)->toBeLessThan(3.0); // Приемлемое время для такого графа
})->group('performance');

