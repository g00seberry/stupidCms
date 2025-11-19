<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;

/**
 * Feature-тесты для Blueprints API
 * 
 * Тестирует CRUD операции для Blueprints
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
});

// LIST tests
test('admin can list blueprints', function () {
    Blueprint::factory()->count(3)->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/blueprints');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'name', 'type', 'is_default'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('can filter blueprints by post_type_id', function () {
    $postType = PostType::factory()->create();
    
    Blueprint::factory()->count(2)->create(['post_type_id' => $postType->id, 'type' => 'full']);
    Blueprint::factory()->create(['post_type_id' => null, 'type' => 'component']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/blueprints?post_type_id={$postType->id}");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('can filter blueprints by type', function () {
    Blueprint::factory()->count(2)->create(['type' => 'full']);
    Blueprint::factory()->count(3)->create(['type' => 'component']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/blueprints?type=component');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

// CREATE tests
test('admin can create full blueprint', function () {
    $postType = PostType::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/blueprints', [
            'post_type_id' => $postType->id,
            'slug' => 'article-full',
            'name' => 'Article Full',
            'description' => 'Full article schema',
            'type' => 'full',
            'is_default' => false,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'article-full')
        ->assertJsonPath('data.type', 'full');

    $this->assertDatabaseHas('blueprints', [
        'slug' => 'article-full',
        'type' => 'full',
        'post_type_id' => $postType->id,
    ]);
});

test('admin can create component blueprint', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/blueprints', [
            'slug' => 'seo-fields',
            'name' => 'SEO Fields',
            'description' => 'SEO component',
            'type' => 'component',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'seo-fields')
        ->assertJsonPath('data.type', 'component')
        ->assertJsonPath('data.post_type_id', null);

    $this->assertDatabaseHas('blueprints', [
        'slug' => 'seo-fields',
        'type' => 'component',
        'post_type_id' => null,
    ]);
});

test('full blueprint requires post_type_id', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/blueprints', [
            'slug' => 'test',
            'name' => 'Test',
            'type' => 'full',
            // post_type_id отсутствует
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['post_type_id']);
});

test('component blueprint cannot have post_type_id', function () {
    $postType = PostType::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/blueprints', [
            'post_type_id' => $postType->id,
            'slug' => 'test-component',
            'name' => 'Test Component',
            'type' => 'component',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['post_type_id']);
});

test('slug must be unique per type and post_type', function () {
    $postType = PostType::factory()->create();
    
    Blueprint::factory()->create([
        'post_type_id' => $postType->id,
        'slug' => 'duplicate',
        'type' => 'full',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/blueprints', [
            'post_type_id' => $postType->id,
            'slug' => 'duplicate',
            'name' => 'Duplicate',
            'type' => 'full',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

test('same slug allowed for different types', function () {
    $postType = PostType::factory()->create();
    
    Blueprint::factory()->create([
        'post_type_id' => $postType->id,
        'slug' => 'shared-slug',
        'type' => 'full',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/blueprints', [
            'slug' => 'shared-slug',
            'name' => 'Component with same slug',
            'type' => 'component',
        ]);

    $response->assertCreated();
});

// SHOW tests
test('admin can view blueprint', function () {
    $blueprint = Blueprint::factory()->create([
        'slug' => 'test-blueprint',
        'name' => 'Test Blueprint',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/blueprints/{$blueprint->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $blueprint->id)
        ->assertJsonPath('data.slug', 'test-blueprint')
        ->assertJsonPath('data.name', 'Test Blueprint');
});

test('show returns 404 for non-existent blueprint', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/blueprints/99999');

    $response->assertNotFound();
});

// UPDATE tests
test('admin can update blueprint', function () {
    $blueprint = Blueprint::factory()->create([
        'slug' => 'old-slug',
        'name' => 'Old Name',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/blueprints/{$blueprint->id}", [
            'name' => 'New Name',
            'description' => 'Updated description',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    $this->assertDatabaseHas('blueprints', [
        'id' => $blueprint->id,
        'name' => 'New Name',
    ]);
});

test('cannot change blueprint type', function () {
    $blueprint = Blueprint::factory()->create(['type' => 'full']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/blueprints/{$blueprint->id}", [
            'type' => 'component',
        ]);

    $freshBlueprint = $blueprint->fresh();
    expect($freshBlueprint->type)->toBe('full');
});

// DELETE tests
test('admin can delete blueprint', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/blueprints/{$blueprint->id}");

    $response->assertNoContent();

    $this->assertSoftDeleted('blueprints', ['id' => $blueprint->id]);
});

test('cannot delete blueprint with entries', function () {
    $blueprint = Blueprint::factory()->create();
    Entry::factory()->count(2)->create(['blueprint_id' => $blueprint->id]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/blueprints/{$blueprint->id}");

    $response->assertStatus(409); // Conflict

    $this->assertDatabaseHas('blueprints', ['id' => $blueprint->id]);
});

test('can force delete blueprint with entries', function () {
    $blueprint = Blueprint::factory()->create();
    Entry::factory()->count(2)->create(['blueprint_id' => $blueprint->id]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/blueprints/{$blueprint->id}?force=1");

    $response->assertNoContent();

    $this->assertDatabaseMissing('blueprints', ['id' => $blueprint->id, 'deleted_at' => null]);
});

test('delete returns 404 for non-existent blueprint', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/blueprints/99999');

    $response->assertNotFound();
});

