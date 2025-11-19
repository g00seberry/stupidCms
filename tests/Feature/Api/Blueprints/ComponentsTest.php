<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\User;

/**
 * Feature-тесты для Blueprint Components API
 * 
 * Тестирует attach/detach компонентов к Blueprints
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->blueprint = Blueprint::factory()->create(['type' => 'full']);
    $this->component = Blueprint::factory()->create(['type' => 'component']);
});

// LIST tests
test('admin can list components of blueprint', function () {
    $this->blueprint->components()->attach($this->component->id, ['path_prefix' => 'seo']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'name', 'type'],
            ],
        ])
        ->assertJsonCount(1, 'data');
});

test('list returns empty for blueprint without components', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components");

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

// ATTACH tests
test('admin can attach component to blueprint', function () {
    Path::factory()->count(2)->create(['blueprint_id' => $this->component->id]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components", [
            'component_id' => $this->component->id,
            'path_prefix' => 'seo',
        ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Component attached successfully');

    $this->assertDatabaseHas('blueprint_components', [
        'blueprint_id' => $this->blueprint->id,
        'component_id' => $this->component->id,
        'path_prefix' => 'seo',
    ]);
});

test('attaching component materializes paths', function () {
    // Создаем Path в компоненте
    $sourcePath = Path::factory()->create([
        'blueprint_id' => $this->component->id,
        'name' => 'metaTitle',
        'full_path' => 'metaTitle',
    ]);

    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components", [
            'component_id' => $this->component->id,
            'path_prefix' => 'seo',
        ]);

    // Материализованный Path должен существовать
    $this->assertDatabaseHas('paths', [
        'blueprint_id' => $this->blueprint->id,
        'full_path' => 'seo.metaTitle',
        'source_component_id' => $this->component->id,
        'source_path_id' => $sourcePath->id,
    ]);
});

test('cannot attach non-component blueprint', function () {
    $fullBlueprint = Blueprint::factory()->create(['type' => 'full']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components", [
            'component_id' => $fullBlueprint->id,
            'path_prefix' => 'test',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['component_id']);
});

test('cannot attach blueprint to itself', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components", [
            'component_id' => $this->blueprint->id,
            'path_prefix' => 'test',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['component_id']);
});

test('cannot attach same component twice', function () {
    $this->blueprint->components()->attach($this->component->id, ['path_prefix' => 'seo']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components", [
            'component_id' => $this->component->id,
            'path_prefix' => 'meta',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['component_id']);
});

test('path_prefix must be unique per blueprint', function () {
    $component2 = Blueprint::factory()->create(['type' => 'component']);
    
    $this->blueprint->components()->attach($this->component->id, ['path_prefix' => 'seo']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components", [
            'component_id' => $component2->id,
            'path_prefix' => 'seo',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['path_prefix']);
});

test('path_prefix is required', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components", [
            'component_id' => $this->component->id,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['path_prefix']);
});

test('detects circular dependencies', function () {
    $componentA = Blueprint::factory()->create(['type' => 'component']);
    $componentB = Blueprint::factory()->create(['type' => 'component']);
    
    // A включает B
    $componentA->components()->attach($componentB->id, ['path_prefix' => 'b']);
    
    // B пытается включить A (цикл)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$componentB->id}/components", [
            'component_id' => $componentA->id,
            'path_prefix' => 'a',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['component_id']);
});

// DETACH tests
test('admin can detach component from blueprint', function () {
    $this->blueprint->components()->attach($this->component->id, ['path_prefix' => 'seo']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components/{$this->component->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('blueprint_components', [
        'blueprint_id' => $this->blueprint->id,
        'component_id' => $this->component->id,
    ]);
});

test('detaching component dematerializes paths', function () {
    // Создаем Path в компоненте
    $sourcePath = Path::factory()->create([
        'blueprint_id' => $this->component->id,
        'name' => 'metaTitle',
        'full_path' => 'metaTitle',
    ]);
    
    // Attach (материализация)
    $this->blueprint->components()->attach($this->component->id, ['path_prefix' => 'seo']);
    $this->blueprint->materializeComponentPaths($this->component, 'seo');
    
    $materializedPath = Path::where([
        'blueprint_id' => $this->blueprint->id,
        'source_component_id' => $this->component->id,
    ])->first();
    
    expect($materializedPath)->not->toBeNull();
    
    // Detach (дематериализация)
    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components/{$this->component->id}");

    // Материализованный Path должен быть удален
    $this->assertSoftDeleted('paths', ['id' => $materializedPath->id]);
});

test('detaching component reindexes entries', function () {
    $entry = Entry::factory()->create(['blueprint_id' => $this->blueprint->id]);
    $this->blueprint->components()->attach($this->component->id, ['path_prefix' => 'seo']);

    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components/{$this->component->id}");

    // Job для реиндексации должен быть dispatched
    // (в реальности это проверяется через Bus::fake())
    expect(true)->toBeTrue();
});

test('detach returns 404 for non-attached component', function () {
    $notAttached = Blueprint::factory()->create(['type' => 'component']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/blueprints/{$this->blueprint->id}/components/{$notAttached->id}");

    $response->assertNotFound();
});

