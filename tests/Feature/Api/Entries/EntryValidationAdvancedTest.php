<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Entries;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->user = User::factory()->create(['is_admin' => true]);
});

// ============================================================================
// FIELD_COMPARISON ПРАВИЛА
// ============================================================================

test('validates field_comparison with field on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'start_date',
        'full_path' => 'start_date',
        'data_type' => 'date',
        'validation_rules' => ['required' => false],
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'end_date',
        'full_path' => 'end_date',
        'data_type' => 'date',
        'validation_rules' => ['required' => false],
        'cardinality' => 'one',
        'validation_rules' => [
            'field_comparison' => ['operator' => '>=', 'field' => 'content_json.start_date'],
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // end_date >= start_date
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-15',
        ],
    ]);

    $response->assertStatus(201);

    // end_date < start_date
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'start_date' => '2025-01-15',
            'end_date' => '2025-01-01',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.end_date']);
});

test('validates field_comparison with constant value on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'published_at',
        'full_path' => 'published_at',
        'data_type' => 'datetime',
        'validation_rules' => ['required' => false],
        'cardinality' => 'one',
        'validation_rules' => [
            'field_comparison' => ['operator' => '>=', 'value' => '2025-01-01'],
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Дата >= константы
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'published_at' => '2025-01-15T10:00:00Z',
        ],
    ]);

    $response->assertStatus(201);

    // Дата < константы
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'published_at' => '2024-12-31T10:00:00Z',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.published_at']);
});

test('validates field_comparison with different operators on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'start_date',
        'full_path' => 'start_date',
        'data_type' => 'date',
        'validation_rules' => ['required' => false],
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'end_date',
        'full_path' => 'end_date',
        'data_type' => 'date',
        'validation_rules' => ['required' => false],
        'cardinality' => 'one',
        'validation_rules' => [
            'field_comparison' => ['operator' => '>', 'field' => 'content_json.start_date'],
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // end_date > start_date
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-02',
        ],
    ]);

    $response->assertStatus(201);

    // end_date = start_date (не проходит для оператора >)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-01',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.end_date']);
});

// ============================================================================
// КОМБИНАЦИИ ПРАВИЛ
// ============================================================================

test('validates field_comparison plus required_if combination on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'start_date',
        'full_path' => 'start_date',
        'data_type' => 'date',
        'validation_rules' => ['required' => false],
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'end_date',
        'full_path' => 'end_date',
        'data_type' => 'date',
        'validation_rules' => ['required' => false],
        'cardinality' => 'one',
        'validation_rules' => [
            'field_comparison' => ['operator' => '>=', 'field' => 'content_json.start_date'],
            'required_if' => ['field' => 'is_published', 'value' => true, 'operator' => '=='],
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // is_published = false, end_date не обязателен
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'is_published' => false,
        'content_json' => [
            'start_date' => '2025-01-01',
        ],
    ]);

    $response->assertStatus(201);

    // is_published = true, end_date отсутствует
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'is_published' => true,
        'content_json' => [
            'start_date' => '2025-01-01',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.end_date']);

    // is_published = true, end_date < start_date
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'is_published' => true,
        'content_json' => [
            'start_date' => '2025-01-15',
            'end_date' => '2025-01-10',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.end_date']);

    // is_published = true, end_date >= start_date
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'is_published' => true,
        'content_json' => [
            'start_date' => '2025-01-10',
            'end_date' => '2025-01-15',
        ],
    ]);

    $response->assertStatus(201);
});

// ============================================================================
// МНОГОУРОВНЕВАЯ ВЛОЖЕННОСТЬ
// ============================================================================

test('validates multi-level nested fields on create', function () {
    $blueprint = Blueprint::factory()->create();
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'data_type' => 'json',
        'validation_rules' => ['required' => true],
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'contacts',
        'full_path' => 'author.contacts',
        'data_type' => 'json',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'phone',
        'full_path' => 'author.contacts.phone',
        'data_type' => 'string',
        'cardinality' => 'one',
        'validation_rules' => [
            'required' => true,
            'pattern' => '^\\+?[1-9]\\d{1,14}$',
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Валидная структура
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'author' => [
                'contacts' => [
                    'phone' => '+1234567890',
                ],
            ],
        ],
    ]);

    $response->assertStatus(201);

    // Отсутствует phone
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'author' => [
                'contacts' => [],
            ],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.author.contacts.phone']);

    // Невалидный phone (не соответствует pattern)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'author' => [
                'contacts' => [
                    'phone' => 'invalid-phone',
                ],
            ],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.author.contacts.phone']);
});

// ============================================================================
// СПЕЦИАЛЬНЫЕ СИМВОЛЫ
// ============================================================================

test('validates special characters in strings on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'validation_rules' => ['required' => true],
        'cardinality' => 'one',
        'validation_rules' => [
            'pattern' => '^[a-zA-Z0-9\\s]+$',
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Валидная строка (только буквы, цифры, пробелы)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'title' => 'Title 123',
        ],
    ]);

    $response->assertStatus(201);

    // Специальные символы
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'title' => 'Title@#$',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.title']);
});

// ============================================================================
// ОБНОВЛЕНИЕ С УСЛОВНЫМИ ПРАВИЛАМИ
// ============================================================================

test('validates conditional rules on update', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'slug',
        'full_path' => 'slug',
        'data_type' => 'string',
        'validation_rules' => ['required' => false],
        'cardinality' => 'one',
        'validation_rules' => [
            'required_if' => ['field' => 'is_published', 'value' => true],
            'min' => 1,
            'max' => 255,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['slug' => 'original-slug'],
        'status' => 'draft',
    ]);

    // Обновляем is_published = true, но slug отсутствует
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
        'is_published' => true,
        'content_json' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.slug']);

    // Обновляем is_published = true, slug присутствует
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
        'is_published' => true,
        'content_json' => [
            'slug' => 'new-slug',
        ],
    ]);

    $response->assertStatus(200);
});

// ============================================================================
// ОТСУТСТВИЕ BLUEPRINT
// ============================================================================

test('validates entry without blueprint on create', function () {
    $postType = PostType::factory()->create(['blueprint_id' => null]);

    // Entry без blueprint - валидация content_json не применяется
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'any_field' => 'any_value',
        ],
    ]);

    $response->assertStatus(201);
});

test('validates entry without blueprint on update', function () {
    $postType = PostType::factory()->create(['blueprint_id' => null]);
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['some_field' => 'some_value'],
    ]);

    // Обновление Entry без blueprint
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
        'content_json' => [
            'any_field' => 'any_value',
        ],
    ]);

    $response->assertStatus(200);
});

