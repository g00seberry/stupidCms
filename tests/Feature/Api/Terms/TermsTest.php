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
    $this->taxonomy = Taxonomy::factory()->create(['hierarchical' => true]);
});

// LIST tests
test('admin can list terms by taxonomy', function () {
    Term::factory()->count(3)->create(['taxonomy_id' => $this->taxonomy->id]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/taxonomies/{$this->taxonomy->id}/terms");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'taxonomy', 'name', 'meta_json', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
        ])
        ->assertJsonPath('meta.total', 3);
});

test('terms list is paginated', function () {
    Term::factory()->count(30)->create(['taxonomy_id' => $this->taxonomy->id]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/taxonomies/{$this->taxonomy->id}/terms?per_page=10");

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonCount(10, 'data');
});

test('terms can be searched by name', function () {
    Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'Laravel']);
    Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'PHP']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/taxonomies/{$this->taxonomy->id}/terms?q=larav");

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Laravel');
});

test('terms can be sorted by name asc', function () {
    Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'Zebra']);
    Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'Alpha']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/taxonomies/{$this->taxonomy->id}/terms?sort=name.asc");

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'Alpha')
        ->assertJsonPath('data.1.name', 'Zebra');
});

test('returns 404 for non-existent taxonomy', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/taxonomies/99999/terms');

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

// TREE tests
test('admin can get terms tree', function () {
    $parent = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'Parent']);
    $child = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'Child']);
    
    // Set hierarchy
    $hierarchyService = app(\App\Support\TermHierarchy\TermHierarchyService::class);
    $hierarchyService->setParent($child, $parent->id);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/taxonomies/{$this->taxonomy->id}/terms/tree");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'children'],
            ],
        ]);
});

// CREATE tests
test('admin can create term', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/taxonomies/{$this->taxonomy->id}/terms", [
            'name' => 'Laravel',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Laravel')
        ->assertJsonPath('data.taxonomy', $this->taxonomy->id);

    expect(Term::where('name', 'Laravel')->exists())->toBeTrue();
});

test('term can have meta_json', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/taxonomies/{$this->taxonomy->id}/terms", [
            'name' => 'Blue',
            'meta_json' => ['color' => '#0000ff'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.meta_json.color', '#0000ff');
});

test('term can have parent', function () {
    $parent = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/taxonomies/{$this->taxonomy->id}/terms", [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

    $response->assertCreated();

    $term = Term::where('name', 'Child')->first();
    expect($term->parent_id)->toBe($parent->id);
});

test('term name is required', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/taxonomies/{$this->taxonomy->id}/terms", [
            'meta_json' => [],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

// SHOW tests
test('admin can view term', function () {
    $term = Term::factory()->create([
        'taxonomy_id' => $this->taxonomy->id,
        'name' => 'Laravel',
    ]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/terms/{$term->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $term->id)
        ->assertJsonPath('data.name', 'Laravel');
});

test('show returns 404 for non-existent term', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/terms/99999');

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

// UPDATE tests
test('admin can update term name', function () {
    $term = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'Old Name']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/terms/{$term->id}", [
            'name' => 'New Name',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    expect($term->fresh()->name)->toBe('New Name');
});

test('admin can update term meta_json', function () {
    $term = Term::factory()->create([
        'taxonomy_id' => $this->taxonomy->id,
        'meta_json' => ['color' => '#ff0000'],
    ]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/terms/{$term->id}", [
            'name' => 'Updated',
            'meta_json' => ['color' => '#00ff00'],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.meta_json.color', '#00ff00');
});

test('admin can change term parent', function () {
    $parent1 = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    $parent2 = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    $term = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    
    $hierarchyService = app(\App\Support\TermHierarchy\TermHierarchyService::class);
    $hierarchyService->setParent($term, $parent1->id);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/terms/{$term->id}", [
            'name' => $term->name,
            'parent_id' => $parent2->id,
        ]);

    $response->assertOk();

    expect($term->fresh()->parent_id)->toBe($parent2->id);
});

test('setting parent_id to null preserves children relationships', function () {
    $parent = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'Parent']);
    $term = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'Term']);
    $child1 = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'Child 1']);
    $child2 = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id, 'name' => 'Child 2']);
    
    $hierarchyService = app(\App\Support\TermHierarchy\TermHierarchyService::class);
    
    // Создаем иерархию: parent -> term -> child1, child2
    $hierarchyService->setParent($term, $parent->id);
    $hierarchyService->setParent($child1, $term->id);
    $hierarchyService->setParent($child2, $term->id);

    // Проверяем начальное состояние
    expect($term->fresh()->parent_id)->toBe($parent->id)
        ->and($child1->fresh()->parent_id)->toBe($term->id)
        ->and($child2->fresh()->parent_id)->toBe($term->id);

    // Делаем term корневым (parent_id = null)
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/terms/{$term->id}", [
            'name' => $term->name,
            'parent_id' => null,
        ]);

    $response->assertOk();

    // Проверяем, что term стал корневым, но его дочерние элементы остались дочерними
    expect($term->fresh()->parent_id)->toBeNull()
        ->and($child1->fresh()->parent_id)->toBe($term->id)
        ->and($child2->fresh()->parent_id)->toBe($term->id);
});

test('update returns 404 for non-existent term', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/terms/99999', [
            'name' => 'Test',
        ]);

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

// DELETE tests
test('admin can delete term', function () {
    $term = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/terms/{$term->id}");

    $response->assertNoContent();

    expect(Term::find($term->id))->toBeNull();
});

test('delete returns 404 for non-existent term', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/terms/99999');

    $response->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

