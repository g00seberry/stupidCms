<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature-тесты для GET /api/v1/admin/entries/search
 *
 * Тестирует поиск записей по заголовку и фильтрацию по типам записей.
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->postType1 = PostType::factory()->create(['name' => 'Article']);
    $this->postType2 = PostType::factory()->create(['name' => 'Page']);
});

test('admin can search entries by title', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
        'title' => 'Landing page',
    ]);

    Entry::factory()->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
        'title' => 'About us',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?filters[title]=Landing');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'post_type', 'title', 'status'],
            ],
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Landing page');
});

test('search entries by title is case insensitive', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
        'title' => 'Landing Page',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?filters[title]=landing');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can filter entries by single post type', function () {
    Entry::factory()->count(3)->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
    ]);

    Entry::factory()->count(2)->create([
        'post_type_id' => $this->postType2->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?filters[post_type_ids][]=' . $this->postType1->id);

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('admin can filter entries by multiple post types', function () {
    Entry::factory()->count(2)->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
    ]);

    Entry::factory()->count(3)->create([
        'post_type_id' => $this->postType2->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?filters[post_type_ids][]=' . $this->postType1->id . '&filters[post_type_ids][]=' . $this->postType2->id);

    $response->assertOk()
        ->assertJsonCount(5, 'data');
});

test('admin can combine title and post type filters', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
        'title' => 'Landing page',
    ]);

    Entry::factory()->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
        'title' => 'About us',
    ]);

    Entry::factory()->create([
        'post_type_id' => $this->postType2->id,
        'author_id' => $this->user->id,
        'title' => 'Landing page',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?filters[title]=Landing&filters[post_type_ids][]=' . $this->postType1->id);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Landing page')
        ->assertJsonPath('data.0.post_type.id', $this->postType1->id)
        ->assertJsonPath('data.0.post_type.name', $this->postType1->name);
});

test('entries are paginated', function () {
    Entry::factory()->count(25)->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?pagination[per_page]=10&pagination[page]=1');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonCount(10, 'data');
});

test('pagination works with page parameter', function () {
    Entry::factory()->count(25)->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?pagination[per_page]=10&pagination[page]=2');

    $response->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(10, 'data');
});

test('returns empty result when no matches found', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
        'title' => 'Test',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?filters[title]=NonExistent');

    $response->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

test('deleted entries are excluded from search results', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
        'title' => 'Test Entry',
    ]);

    $entry->delete();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

test('returns all entries when no filters provided', function () {
    Entry::factory()->count(5)->create([
        'post_type_id' => $this->postType1->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search');

    $response->assertOk()
        ->assertJsonCount(5, 'data');
});

test('validates title max length', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?filters[title]=' . str_repeat('a', 501));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['filters.title']);
});

test('validates post_type_ids exist', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?filters[post_type_ids][]=99999');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['filters.post_type_ids.0']);
});

test('validates per_page minimum', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?pagination[per_page]=5');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['pagination.per_page']);
});

test('validates per_page maximum', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?pagination[per_page]=101');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['pagination.per_page']);
});

test('validates page minimum', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search?pagination[page]=0');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['pagination.page']);
});

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/v1/admin/entries/search');

    $response->assertUnauthorized();
});

test('user without manage.entries permission returns 403', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class])
        ->getJson('/api/v1/admin/entries/search');

    $response->assertForbidden();
});

