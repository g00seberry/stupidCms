<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\PostType;
use App\Models\Entry;

/**
 * Feature-тесты для PostTypes API
 * 
 * Тестирует CRUD операции для типов записей
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
});

// LIST tests
test('admin can list post types', function () {
    PostType::factory()->count(3)->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/post-types');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['name', 'template', 'options_json'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('post types are sorted by name', function () {
    PostType::factory()->create(['name' => 'Zebra']);
    PostType::factory()->create(['name' => 'Article']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/post-types');

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'Article')
        ->assertJsonPath('data.1.name', 'Zebra');
});

// CREATE tests
test('admin can create post type', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/post-types', [
            'name' => 'Products',
            'template' => 'templates.product',
            'options_json' => ['fields' => ['price' => ['type' => 'number']]],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Products')
        ->assertJsonPath('data.template', 'templates.product');

    $this->assertDatabaseHas('post_types', [
        'name' => 'Products',
        'template' => 'templates.product',
    ]);
});

test('admin can create post type without template', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/post-types', [
            'name' => 'Products',
            'options_json' => [],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Products')
        ->assertJsonPath('data.template', null);
});

test('post type validation fails with missing name', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/post-types', [
            'slug' => 'product',
            'options_json' => [],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('post type can be created with custom fields in options', function () {
    $options = [
        'fields' => [
            'price' => ['type' => 'number'],
            'sku' => ['type' => 'string'],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/post-types', [
            'name' => 'Products',
            'options_json' => $options,
        ]);

    $response->assertCreated();
    
    $postType = PostType::where('name', 'Products')->first();
    expect($postType->options_json->toArray())->toMatchArray($options);
});

// SHOW tests
test('admin can view post type', function () {
    $postType = PostType::factory()->create(['name' => 'Articles', 'template' => 'templates.article']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/post-types/{$postType->id}");

    $response->assertOk()
        ->assertJsonPath('data.name', 'Articles')
        ->assertJsonPath('data.template', 'templates.article');
});

test('show not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/post-types/99999');

    $response->assertNotFound();
});

// UPDATE tests
test('admin can update post type', function () {
    $postType = PostType::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/post-types/{$postType->id}", [
            'name' => 'New Name',
            'options_json' => [],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    $this->assertDatabaseHas('post_types', [
        'id' => $postType->id,
        'name' => 'New Name',
    ]);
});

test('post type template can be updated', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/post-types/{$postType->id}", [
            'template' => 'templates.article',
            'options_json' => [],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.template', 'templates.article');

    $this->assertDatabaseHas('post_types', [
        'id' => $postType->id,
        'template' => 'templates.article',
    ]);
});

test('post type options can be updated', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);

    $newOptions = ['fields' => ['hero' => ['type' => 'image']]];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/post-types/{$postType->id}", [
            'options_json' => $newOptions,
        ]);

    $response->assertOk();
    
    $freshPostType = $postType->fresh();
    expect($freshPostType->options_json->toArray())->toMatchArray($newOptions);
});

test('update not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/post-types/99999', [
            'name' => 'New Name',
            'options_json' => [],
        ]);

    $response->assertNotFound();
});

// DELETE tests
test('admin can delete post type', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/post-types/{$postType->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('post_types', ['id' => $postType->id]);
});

test('cannot delete post type with entries', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    Entry::factory()->count(3)->create(['post_type_id' => $postType->id]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/post-types/{$postType->id}");

    $response->assertStatus(409); // Conflict

    $this->assertDatabaseHas('post_types', ['id' => $postType->id]);
});

test('can force delete post type with entries', function () {
    $postType = PostType::factory()->create(['name' => 'Article']);
    Entry::factory()->count(3)->create(['post_type_id' => $postType->id]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/post-types/{$postType->id}?force=1");

    $response->assertNoContent();

    $this->assertDatabaseMissing('post_types', ['id' => $postType->id]);
    $this->assertDatabaseMissing('entries', ['post_type_id' => $postType->id]);
});

test('delete not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/post-types/99999');

    $response->assertNotFound();
});

