<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\User;

/**
 * Feature-тесты для Paths API
 * 
 * Тестирует CRUD операции для Paths внутри Blueprint
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->blueprint = Blueprint::factory()->create(['type' => 'full']);
});

// LIST tests
test('admin can list paths in blueprint', function () {
    Path::factory()->count(3)->create(['blueprint_id' => $this->blueprint->id]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'full_path', 'data_type', 'cardinality'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('can filter own paths only', function () {
    // Собственные пути
    Path::factory()->count(2)->create([
        'blueprint_id' => $this->blueprint->id,
        'source_component_id' => null,
    ]);
    
    // Материализованный путь
    $component = Blueprint::factory()->create(['type' => 'component']);
    $sourcePath = Path::factory()->create(['blueprint_id' => $component->id]);
    Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'source_component_id' => $component->id,
        'source_path_id' => $sourcePath->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths?own_only=true");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// CREATE tests
test('admin can create path', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
            'name' => 'title',
            'full_path' => 'title',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => true,
            'is_required' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'title')
        ->assertJsonPath('data.full_path', 'title')
        ->assertJsonPath('data.is_indexed', true);

    $this->assertDatabaseHas('paths', [
        'blueprint_id' => $this->blueprint->id,
        'full_path' => 'title',
        'data_type' => 'string',
    ]);
});

test('path full_path must be unique per blueprint', function () {
    Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'full_path' => 'content',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
            'name' => 'content2',
            'full_path' => 'content',
            'data_type' => 'text',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['full_path']);
});

test('ref type path requires ref_target_type', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
            'name' => 'related',
            'full_path' => 'related',
            'data_type' => 'ref',
            'cardinality' => 'many',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ref_target_type']);
});

test('component blueprint cannot have parent_id', function () {
    $component = Blueprint::factory()->create(['type' => 'component']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$component->id}/paths", [
            'parent_id' => 1,
            'name' => 'field',
            'full_path' => 'field',
            'data_type' => 'string',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('can create path with validation rules', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
            'name' => 'email',
            'full_path' => 'email',
            'data_type' => 'string',
            'validation_rules' => ['email', 'max:255'],
        ]);

    $response->assertCreated();
    
    $path = Path::where('full_path', 'email')->first();
    expect($path->validation_rules)->toBe(['email', 'max:255']);
});

test('can create path with ui options', function () {
    $uiOptions = [
        'placeholder' => 'Enter email',
        'help' => 'Contact email',
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
            'name' => 'email',
            'full_path' => 'email',
            'data_type' => 'string',
            'ui_options' => $uiOptions,
        ]);

    $response->assertCreated();
    
    $path = Path::where('full_path', 'email')->first();
    expect($path->ui_options)->toBe($uiOptions);
});

// SHOW tests
test('admin can view path', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'full_path' => 'content',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths/{$path->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $path->id)
        ->assertJsonPath('data.full_path', 'content');
});

test('show returns 404 for non-existent path', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths/99999");

    $response->assertNotFound();
});

// UPDATE tests
test('admin can update path', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'is_indexed' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths/{$path->id}", [
            'is_indexed' => true,
            'is_required' => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.is_indexed', true);

    $this->assertDatabaseHas('paths', [
        'id' => $path->id,
        'is_indexed' => true,
        'is_required' => true,
    ]);
});

// DELETE tests
test('admin can delete path', function () {
    $path = Path::factory()->create(['blueprint_id' => $this->blueprint->id]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths/{$path->id}");

    $response->assertNoContent();

    $this->assertSoftDeleted('paths', ['id' => $path->id]);
});

test('deleting path dematerializes copies in other blueprints', function () {
    // Component с Path
    $component = Blueprint::factory()->create(['type' => 'component']);
    $sourcePath = Path::factory()->create([
        'blueprint_id' => $component->id,
        'full_path' => 'field',
    ]);
    
    // Материализованная копия
    $materializedPath = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'full_path' => 'prefix.field',
        'source_component_id' => $component->id,
        'source_path_id' => $sourcePath->id,
    ]);
    
    // Удаляем исходный Path
    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/blueprints/{$component->id}/paths/{$sourcePath->id}");

    // Материализованная копия тоже должна быть удалена
    $this->assertSoftDeleted('paths', ['id' => $materializedPath->id]);
});

test('delete returns 404 for non-existent path', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths/99999");

    $response->assertNotFound();
});

