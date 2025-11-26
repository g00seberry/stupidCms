<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Entries;

use App\Models\Blueprint;
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

test('validates content_json with min max rules on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => [
            'min' => 1,
            'max' => 500,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Слишком короткое значение
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'title' => '', // Пустая строка меньше min:1
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.title']);

    // Слишком длинное значение
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'title' => str_repeat('a', 501), // Превышает max:500
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.title']);

    // Валидное значение
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'title' => 'Valid Title',
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with pattern rules on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'phone',
        'full_path' => 'phone',
        'data_type' => 'string',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => [
            'pattern' => '^\\+?[1-9]\\d{1,14}$',
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Невалидный паттерн
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'phone' => 'invalid-phone',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.phone']);

    // Валидный паттерн
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'phone' => '+1234567890',
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with required fields on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Отсутствует обязательное поле
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.title']);

    // Поле присутствует
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'title' => 'Content Title',
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with nested paths on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'name',
        'full_path' => 'author.name',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => [
            'min' => 2,
            'max' => 100,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Отсутствует вложенное поле
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'author' => [],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.author.name']);

    // Валидное вложенное поле
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'author' => [
                'name' => 'John Doe',
            ],
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with cardinality many on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'many',
        'validation_rules' => [
            'min' => 1,
            'max' => 50,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Отсутствует обязательный массив
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.tags']);

    // Элемент массива не соответствует правилам
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => [str_repeat('a', 51)], // Превышает max:50
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.tags.0']);

    // Валидный массив
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => ['tag1', 'tag2', 'tag3'],
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with array min items rule on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'many',
        'validation_rules' => [
            'array_min_items' => 2,
            'min' => 1,
            'max' => 50,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Массив с недостаточным количеством элементов (меньше min_items)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => ['tag1'], // Только 1 элемент, требуется минимум 2
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.tags']);

    // Валидный массив (минимум 2 элемента)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => ['tag1', 'tag2'],
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with array max items rule on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'many',
        'validation_rules' => [
            'array_max_items' => 5,
            'min' => 1,
            'max' => 50,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Массив с превышающим количеством элементов (больше max_items)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5', 'tag6'], // 6 элементов, максимум 5
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.tags']);

    // Валидный массив (максимум 5 элементов)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5'],
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with array min and max items rules together on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'many',
        'validation_rules' => [
            'array_min_items' => 2,
            'array_max_items' => 5,
            'min' => 1,
            'max' => 50,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Массив с недостаточным количеством элементов
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => ['tag1'], // Меньше минимума
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.tags']);

    // Массив с превышающим количеством элементов
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5', 'tag6'], // Больше максимума
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.tags']);

    // Валидный массив (от 2 до 5 элементов)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => ['tag1', 'tag2', 'tag3'],
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with required_if conditional rule on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'slug',
        'full_path' => 'slug',
        'data_type' => 'string',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => [
            'required_if' => ['field' => 'is_published', 'value' => true],
            'min' => 1,
            'max' => 255,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // is_published = true, но slug отсутствует - должна быть ошибка
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'is_published' => true,
        'content_json' => [
            // slug отсутствует
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.slug']);

    // is_published = false, slug не обязателен - должно пройти
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'is_published' => false,
        'content_json' => [
            // slug отсутствует, но это OK
        ],
    ]);

    $response->assertStatus(201);

    // is_published = true, slug присутствует - должно пройти
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'is_published' => true,
        'content_json' => [
            'slug' => 'test-slug',
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with prohibited_unless conditional rule on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'draft_note',
        'full_path' => 'draft_note',
        'data_type' => 'text',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => [
            'prohibited_unless' => ['field' => 'is_published', 'value' => false],
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // is_published = true, но draft_note присутствует - должна быть ошибка
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'is_published' => true,
        'content_json' => [
            'draft_note' => 'This should not be here',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.draft_note']);

    // is_published = false, draft_note может присутствовать - должно пройти
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'is_published' => false,
        'content_json' => [
            'draft_note' => 'This is OK for drafts',
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with array unique rule on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'many',
        'validation_rules' => [
            'array_unique' => true,
            'min' => 1,
            'max' => 50,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Массив с дублирующимися элементами
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => ['tag1', 'tag2', 'tag1'], // Дубликат tag1
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.tags.2']); // Ошибка на дубликате

    // Валидный массив с уникальными элементами
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'tags' => ['tag1', 'tag2', 'tag3'],
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with exists rule for ref type on create', function () {
    $blueprint = Blueprint::factory()->create();
    
    // Создаём несколько Entry для ссылок
    $referencedEntry1 = \App\Models\Entry::factory()->create();
    $referencedEntry2 = \App\Models\Entry::factory()->create();
    
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'related_entry',
        'full_path' => 'related_entry',
        'data_type' => 'ref',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => [
            'exists' => ['table' => 'entries', 'column' => 'id'],
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Несуществующий ID
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'related_entry' => 'non-existent-id',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.related_entry']);

    // Валидный ID
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'related_entry' => $referencedEntry1->id,
        ],
    ]);

    $response->assertStatus(201);
});

test('validates content_json with min max rules on update', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => [
            'min' => 1,
            'max' => 500,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    $entry = \App\Models\Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['title' => 'Original Title'],
    ]);

    // Слишком короткое значение
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
        'content_json' => [
            'title' => '', // Пустая строка меньше min:1
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.title']);

    // Валидное значение
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
        'content_json' => [
            'title' => 'Updated Title',
        ],
    ]);

    $response->assertStatus(200);
});

test('validates content_json with integer type min max on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'count',
        'full_path' => 'count',
        'data_type' => 'int',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => [
            'min' => 0,
            'max' => 100,
        ],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Значение меньше min
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'count' => -1,
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.count']);

    // Значение больше max
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'count' => 101,
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.count']);

    // Валидное значение
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'count' => 50,
        ],
    ]);

    $response->assertStatus(201);
});

test('allows nullable optional fields on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'description',
        'full_path' => 'description',
        'data_type' => 'text',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Поле отсутствует (nullable)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [],
    ]);

    $response->assertStatus(201);

    // Поле присутствует
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'description' => 'Some description',
        ],
    ]);

    $response->assertStatus(201);
});

test('validates multiple fields with different rules on create', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_required' => true,
        'cardinality' => 'one',
        'validation_rules' => ['min' => 1, 'max' => 500],
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'count',
        'full_path' => 'count',
        'data_type' => 'int',
        'is_required' => false,
        'cardinality' => 'one',
        'validation_rules' => ['min' => 0, 'max' => 100],
    ]);

    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    // Оба поля невалидны
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'title' => '', // Невалидно
            'count' => 101, // Невалидно
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_json.title', 'content_json.count']);

    // Валидные значения
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
        'post_type' => $postType->slug,
        'title' => 'Test Entry',
        'content_json' => [
            'title' => 'Valid Title',
            'count' => 50,
        ],
    ]);

    $response->assertStatus(201);
});

