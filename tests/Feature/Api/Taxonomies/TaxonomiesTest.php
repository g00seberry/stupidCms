<?php

declare(strict_types=1);

use App\Models\Taxonomy;
use App\Models\Term;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\deleteJson;

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
});

// INDEX tests
test('admin can list taxonomies', function () {
    Taxonomy::factory()->count(3)->create();

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/taxonomies');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'label', 'hierarchical', 'options_json', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
        ]);
});

test('taxonomies list is paginated', function () {
    Taxonomy::factory()->count(30)->create();

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/taxonomies?per_page=10');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonCount(10, 'data');
});

test('taxonomies can be searched by name', function () {
    Taxonomy::factory()->create(['label' => 'Categories']);
    Taxonomy::factory()->create(['label' => 'Tags']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/taxonomies?q=categ');

    $response->assertOk();
    // Может быть больше из-за данных из других тестов, но должно быть минимум 1
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(1);
    // Проверяем, что хотя бы один результат содержит "Categories"
    $hasCategories = false;
    foreach ($response->json('data') as $taxonomy) {
        if (stripos($taxonomy['label'], 'Categories') !== false) {
            $hasCategories = true;
            break;
        }
    }
    expect($hasCategories)->toBeTrue();
});

test('taxonomies can be sorted by created_at desc', function () {
    $old = Taxonomy::factory()->create(['created_at' => now()->subDay()]);
    $new = Taxonomy::factory()->create(['created_at' => now()]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/taxonomies?sort=created_at.desc');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id);
});

test('taxonomies can be sorted by label asc', function () {
    $zebra = Taxonomy::factory()->create(['label' => 'Zebra']);
    $alpha = Taxonomy::factory()->create(['label' => 'Alpha']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/taxonomies?sort=label.asc');

    $response->assertOk();
    $data = $response->json('data');
    // Находим индексы наших таксономий в отсортированном списке
    $alphaIndex = null;
    $zebraIndex = null;
    foreach ($data as $index => $taxonomy) {
        if ($taxonomy['id'] === $alpha->id) {
            $alphaIndex = $index;
        }
        if ($taxonomy['id'] === $zebra->id) {
            $zebraIndex = $index;
        }
    }
    // Alpha должен быть раньше Zebra при сортировке по возрастанию
    expect($alphaIndex)->not->toBeNull();
    expect($zebraIndex)->not->toBeNull();
    expect($alphaIndex)->toBeLessThan($zebraIndex);
});

// CREATE tests
test('admin can create taxonomy', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/taxonomies', [
            'label' => 'Categories',
            'hierarchical' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.label', 'Categories')
        ->assertJsonPath('data.hierarchical', true);

    expect(Taxonomy::where('name', 'Categories')->exists())->toBeTrue();
});

test('taxonomy defaults to non-hierarchical', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/taxonomies', [
            'label' => 'Tags',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.hierarchical', false);
});

test('taxonomy can have options_json', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/taxonomies', [
            'label' => 'Colors',
            'options_json' => ['color' => '#ff0000'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.options_json.color', '#ff0000');
});

test('taxonomy label is required', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/taxonomies', [
            'hierarchical' => true,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['label']);
});

// SHOW tests
test('admin can view taxonomy', function () {
    $taxonomy = Taxonomy::factory()->create([
        'label' => 'Categories',
        'hierarchical' => true,
    ]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/taxonomies/{$taxonomy->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $taxonomy->id)
        ->assertJsonPath('data.label', 'Categories')
        ->assertJsonPath('data.hierarchical', true);
});

test('show returns 404 for non-existent taxonomy', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/taxonomies/99999');

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

// UPDATE tests
test('admin can update taxonomy label', function () {
    $taxonomy = Taxonomy::factory()->create(['label' => 'Old Name']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/taxonomies/{$taxonomy->id}", [
            'label' => 'New Name',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.label', 'New Name');

    expect($taxonomy->fresh()->label)->toBe('New Name');
});

test('admin can update taxonomy hierarchical flag', function () {
    $taxonomy = Taxonomy::factory()->create(['hierarchical' => false]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/taxonomies/{$taxonomy->id}", [
            'label' => 'Updated',
            'hierarchical' => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.hierarchical', true);
});

test('admin can update taxonomy options_json', function () {
    $taxonomy = Taxonomy::factory()->create(['options_json' => ['color' => '#ff0000']]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/taxonomies/{$taxonomy->id}", [
            'label' => 'Updated',
            'options_json' => ['color' => '#00ff00'],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.options_json.color', '#00ff00');
});

test('update returns 404 for non-existent taxonomy', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/taxonomies/99999', [
            'label' => 'Test',
        ]);

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

// DELETE tests
test('admin can delete taxonomy without terms', function () {
    $taxonomy = Taxonomy::factory()->create();

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/taxonomies/{$taxonomy->id}");

    $response->assertNoContent();

    expect(Taxonomy::find($taxonomy->id))->toBeNull();
});

test('cannot delete taxonomy with terms', function () {
    $taxonomy = Taxonomy::factory()->create();
    Term::factory()->create(['taxonomy_id' => $taxonomy->id]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/taxonomies/{$taxonomy->id}");

    $response->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    expect(Taxonomy::find($taxonomy->id))->not->toBeNull();
});

test('can force delete taxonomy with terms', function () {
    $taxonomy = Taxonomy::factory()->create();
    Term::factory()->create(['taxonomy_id' => $taxonomy->id]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/taxonomies/{$taxonomy->id}?force=1");

    $response->assertNoContent();

    expect(Taxonomy::find($taxonomy->id))->toBeNull();
});

test('delete returns 404 for non-existent taxonomy', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/taxonomies/99999');

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

