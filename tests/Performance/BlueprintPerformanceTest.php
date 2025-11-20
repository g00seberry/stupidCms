<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Blueprint\BlueprintStructureService;

test('материализация blueprint с 100 полями < 1s', function () {
    $service = app(BlueprintStructureService::class);

    $host = $service->createBlueprint(['name' => 'Host', 'code' => 'host']);
    $embedded = $service->createBlueprint(['name' => 'Embedded', 'code' => 'embedded']);

    // Создать 100 полей в embedded
    for ($i = 0; $i < 100; $i++) {
        Path::factory()->create([
            'blueprint_id' => $embedded->id,
            'name' => "field_{$i}",
            'full_path' => "field_{$i}",
        ]);
    }

    $start = microtime(true);

    $service->createEmbed($host, $embedded);

    $duration = (microtime(true) - $start) * 1000; // ms

    expect($duration)->toBeLessThan(1000); // < 1s
})->skip('Performance test');

test('индексация Entry с 50 полями < 100ms', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Создать 50 индексируемых полей
    for ($i = 0; $i < 50; $i++) {
        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'name' => "field_{$i}",
            'full_path' => "field_{$i}",
            'data_type' => 'string',
            'is_indexed' => true,
        ]);
    }

    $data = [];
    for ($i = 0; $i < 50; $i++) {
        $data["field_{$i}"] = "value_{$i}";
    }

    $start = microtime(true);

    Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'Test',
        'data_json' => $data,
    ]);

    $duration = (microtime(true) - $start) * 1000; // ms

    expect($duration)->toBeLessThan(100); // < 100ms
})->skip('Performance test');

test('запрос wherePath по 10000 Entry < 50ms', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'category',
        'full_path' => 'category',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    // Создать 10000 Entry
    for ($i = 0; $i < 10000; $i++) {
        Entry::create([
            'post_type_id' => $postType->id,
            'title' => "Entry {$i}",
            'data_json' => ['category' => $i % 10 === 0 ? 'target' : 'other'],
        ]);
    }

    $start = microtime(true);

    $entries = Entry::wherePath('category', '=', 'target')->get();

    $duration = (microtime(true) - $start) * 1000; // ms

    expect($entries)->toHaveCount(1000)
        ->and($duration)->toBeLessThan(50); // < 50ms с индексами
})->skip('Performance test');

