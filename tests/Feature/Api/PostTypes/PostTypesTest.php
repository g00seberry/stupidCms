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
                '*' => ['slug', 'name', 'options_json'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('post types are sorted by slug', function () {
    PostType::factory()->create(['slug' => 'zebra']);
    PostType::factory()->create(['slug' => 'article']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/post-types');

    $response->assertOk()
        ->assertJsonPath('data.0.slug', 'article')
        ->assertJsonPath('data.1.slug', 'zebra');
});

// CREATE tests
test('admin can create post type', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/post-types', [
            'slug' => 'product',
            'name' => 'Products',
            'options_json' => ['fields' => ['price' => ['type' => 'number']]],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'product')
        ->assertJsonPath('data.name', 'Products');

    $this->assertDatabaseHas('post_types', [
        'slug' => 'product',
        'name' => 'Products',
    ]);
});

test('post type slug is unique', function () {
    PostType::factory()->create(['slug' => 'article']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/post-types', [
            'slug' => 'article',
            'name' => 'Articles',
            'options_json' => [],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

test('post type validation fails with missing slug', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/post-types', [
            'name' => 'Products',
            'options_json' => [],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
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
            'slug' => 'product',
            'name' => 'Products',
            'options_json' => $options,
        ]);

    $response->assertCreated();
    
    $postType = PostType::where('slug', 'product')->first();
    expect($postType->options_json->toArray())->toMatchArray($options);
});

// SHOW tests
test('admin can view post type', function () {
    $postType = PostType::factory()->create(['slug' => 'article', 'name' => 'Articles']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/post-types/article');

    $response->assertOk()
        ->assertJsonPath('data.slug', 'article')
        ->assertJsonPath('data.name', 'Articles');
});

test('show not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/post-types/non-existent');

    $response->assertNotFound();
});

// UPDATE tests
test('admin can update post type', function () {
    $postType = PostType::factory()->create(['slug' => 'article', 'name' => 'Old Name']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/post-types/article', [
            'name' => 'New Name',
            'options_json' => [],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    $this->assertDatabaseHas('post_types', [
        'slug' => 'article',
        'name' => 'New Name',
    ]);
});

test('post type slug can be updated', function () {
    $postType = PostType::factory()->create(['slug' => 'article']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/post-types/article', [
            'slug' => 'post',
            'options_json' => [],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.slug', 'post');

    $this->assertDatabaseHas('post_types', ['slug' => 'post']);
    $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
});

test('post type options can be updated', function () {
    $postType = PostType::factory()->create(['slug' => 'article']);

    $newOptions = ['fields' => ['hero' => ['type' => 'image']]];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/post-types/article', [
            'options_json' => $newOptions,
        ]);

    $response->assertOk();
    
    $freshPostType = $postType->fresh();
    expect($freshPostType->options_json->toArray())->toMatchArray($newOptions);
});

test('update not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/post-types/non-existent', [
            'name' => 'New Name',
            'options_json' => [],
        ]);

    $response->assertNotFound();
});

// DELETE tests
test('admin can delete post type', function () {
    $postType = PostType::factory()->create(['slug' => 'article']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/post-types/article');

    $response->assertNoContent();

    $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
});

test('cannot delete post type with entries', function () {
    $postType = PostType::factory()->create(['slug' => 'article']);
    Entry::factory()->count(3)->create(['post_type_id' => $postType->id]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/post-types/article');

    $response->assertStatus(409); // Conflict

    $this->assertDatabaseHas('post_types', ['slug' => 'article']);
});

test('can force delete post type with entries', function () {
    $postType = PostType::factory()->create(['slug' => 'article']);
    Entry::factory()->count(3)->create(['post_type_id' => $postType->id]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/post-types/article?force=1');

    $response->assertNoContent();

    $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
    $this->assertDatabaseMissing('entries', ['post_type_id' => $postType->id]);
});

test('delete not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/post-types/non-existent');

    $response->assertNotFound();
});

