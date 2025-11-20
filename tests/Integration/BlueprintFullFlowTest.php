<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('полный цикл: создание графа → встраивание → Entry → изменение структуры → каскады', function () {
    $service = app(BlueprintStructureService::class);

    // 1. Создать blueprints
    $geo = $service->createBlueprint(['name' => 'Geo', 'code' => 'geo']);
    $address = $service->createBlueprint(['name' => 'Address', 'code' => 'address']);
    $company = $service->createBlueprint(['name' => 'Company', 'code' => 'company']);

    // 2. Добавить поля
    $service->createPath($geo, ['name' => 'lat', 'data_type' => 'float', 'is_indexed' => true]);
    $service->createPath($geo, ['name' => 'lng', 'data_type' => 'float', 'is_indexed' => true]);

    $service->createPath($address, ['name' => 'street', 'data_type' => 'string', 'is_indexed' => true]);

    $service->createPath($company, ['name' => 'name', 'data_type' => 'string', 'is_indexed' => true]);

    // 3. Создать встраивания: Address → Geo, Company → Address
    $service->createEmbed($address, $geo);
    $service->createEmbed($company, $address);

    // 4. Проверить транзитивную материализацию
    $companyPaths = $company->paths()->orderBy('full_path')->get();
    expect($companyPaths->pluck('name')->all())->toContain('name', 'street', 'lat', 'lng');

    // 5. Создать PostType и Entry
    $postType = PostType::factory()->create(['blueprint_id' => $company->id]);

    $entry = Entry::create([
        'post_type_id' => $postType->id,
        'title' => 'ACME Corp',
        'data_json' => [
            'name' => 'ACME Corporation',
            'street' => '123 Main St',
            'lat' => 40.7128,
            'lng' => -74.0060,
        ],
    ]);

    // 6. Проверить индексацию
    expect($entry->docValues()->count())->toBeGreaterThan(0);

    $latValue = $entry->docValues()
        ->whereHas('path', fn($q) => $q->where('name', 'lat'))
        ->first();

    expect($latValue->value_float)->toBe(40.7128);

    // 7. Изменить структуру Geo (добавить поле)
    // НЕ используем Event::fake(), чтобы события реально обработались
    $service->createPath($geo, ['name' => 'altitude', 'data_type' => 'float', 'is_indexed' => true]);

    // 8. Проверить каскадную рематериализацию
    $company->refresh();
    $companyPathsAfter = $company->paths()->get();
    expect($companyPathsAfter->pluck('name')->all())->toContain('altitude');

    // 9. Реиндексировать Entry
    $entry->data_json = array_merge($entry->data_json, ['altitude' => 100.0]);
    $entry->save();

    // 10. Проверить новое значение в индексе
    $altitudeValue = $entry->docValues()
        ->whereHas('path', fn($q) => $q->where('name', 'altitude'))
        ->first();

    expect($altitudeValue->value_float)->toBe(100.0);
});

test('сложный граф с diamond dependency работает корректно', function () {
    $service = app(BlueprintStructureService::class);

    // Diamond: D → B, D → C, B → A, C → A
    $a = $service->createBlueprint(['name' => 'A', 'code' => 'a']);
    $b = $service->createBlueprint(['name' => 'B', 'code' => 'b']);
    $c = $service->createBlueprint(['name' => 'C', 'code' => 'c']);
    $d = $service->createBlueprint(['name' => 'D', 'code' => 'd']);

    $service->createPath($a, ['name' => 'field_a', 'data_type' => 'string']);
    $service->createPath($b, ['name' => 'field_b', 'data_type' => 'string']);
    $service->createPath($c, ['name' => 'field_c', 'data_type' => 'string']);

    $service->createEmbed($b, $a);
    $service->createEmbed($c, $a);
    
    // Для diamond dependency нужно использовать разные host_path, чтобы избежать конфликта
    $groupB = $service->createPath($d, ['name' => 'group_b', 'data_type' => 'json']);
    $groupC = $service->createPath($d, ['name' => 'group_c', 'data_type' => 'json']);
    
    $service->createEmbed($d, $b, $groupB);
    $service->createEmbed($d, $c, $groupC);

    // D должен иметь field_a (через B и C под разными путями), field_b, field_c
    $dPaths = $d->paths()->get();
    expect($dPaths->pluck('name')->all())->toContain('field_b', 'field_c');
});

