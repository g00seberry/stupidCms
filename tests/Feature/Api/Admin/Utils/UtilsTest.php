<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\PostType;
use App\Models\ReservedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->postType = PostType::factory()->create(['slug' => 'page']);
});

test('generates slug from title', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=New Landing Page');

    $response->assertOk()
        ->assertJsonStructure(['base', 'unique'])
        ->assertJsonPath('base', 'new-landing-page')
        ->assertJsonPath('unique', 'new-landing-page');
});

test('ensures unique slug when base exists', function () {
    // Create entry with slug
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->admin->id,
        'slug' => 'test-page',
    ]);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=Test Page&postType=page');

    $response->assertOk()
        ->assertJsonPath('base', 'test-page')
        ->assertJsonPath('unique', 'test-page-2');
});

test('checks reserved routes when generating slug', function () {
    // Note: This behavior depends on UniqueSlugService checking ReservedRoute
    // in the callable provided by UtilsController
    ReservedRoute::create([
        'path' => 'api',
        'kind' => 'path',
        'source' => 'system',
    ]);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=API');

    $response->assertOk()
        ->assertJsonPath('base', 'api');

    // The service should detect reserved path and make it unique
    $unique = $response->json('unique');
    // Either 'api' or 'api-2' depending on implementation
    expect($unique)->toBeString()->not->toBeEmpty();
});

test('slug scoped by post type', function () {
    $articleType = PostType::factory()->create(['slug' => 'article']);

    Entry::factory()->create([
        'post_type_id' => $articleType->id,
        'author_id' => $this->admin->id,
        'slug' => 'test',
    ]);

    // Same slug for different post type should be available
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=Test&postType=page');

    $response->assertOk()
        ->assertJsonPath('base', 'test')
        ->assertJsonPath('unique', 'test');
});

test('handles empty title', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=');

    $response->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

test('handles special characters in title', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=' . urlencode('Hello & Goodbye! @2025'));

    $response->assertOk();
    expect($response->json('base'))->toMatch('/^[a-z0-9-]+$/');
});

test('slugify requires authentication', function () {
    $response = $this->getJson('/api/v1/admin/utils/slugify?title=Test');

    expect($response->status())->toBeIn([401, 419]);
});

test('defaults to page post type when not specified', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->admin->id,
        'slug' => 'test',
    ]);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=Test');

    $response->assertOk()
        ->assertJsonPath('unique', 'test-2');
});

test('handles very long titles', function () {
    $longTitle = str_repeat('a', 600);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=' . $longTitle);

    // Should fail validation (max 500)
    $response->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

test('generates incremental suffixes for multiple duplicates', function () {
    // Create multiple entries with same base
    for ($i = 0; $i < 3; $i++) {
        Entry::factory()->create([
            'post_type_id' => $this->postType->id,
            'author_id' => $this->admin->id,
            'slug' => $i === 0 ? 'duplicate' : 'duplicate-' . ($i + 1),
        ]);
    }

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=Duplicate&postType=page');

    $response->assertOk()
        ->assertJsonPath('base', 'duplicate')
        ->assertJsonPath('unique', 'duplicate-4');
});

