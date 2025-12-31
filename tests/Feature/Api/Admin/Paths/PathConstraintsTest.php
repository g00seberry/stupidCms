<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\PathRefConstraint;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class]);

    $this->blueprint = Blueprint::factory()->create();
    $this->postType1 = PostType::factory()->create();
    $this->postType2 = PostType::factory()->create();
    $this->postType3 = PostType::factory()->create();
});

// Тесты создания Path с constraints

test('constraints сохраняются в БД при создании Path с ref-полем', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'author',
        'data_type' => 'ref',
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType1->id, $this->postType2->id],
        ],
    ]);

    $response->assertCreated();
    
    $path = Path::where('name', 'author')->first();
    expect($path)->not->toBeNull();
    
    // Проверить, что constraints созданы в БД
    $constraints = PathRefConstraint::where('path_id', $path->id)->get();
    expect($constraints)->toHaveCount(2);
    expect($constraints->pluck('allowed_post_type_id')->toArray())
        ->toContain($this->postType1->id, $this->postType2->id);
});

test('constraints не создаются при создании Path без constraints', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'title',
        'data_type' => 'string',
    ]);

    $response->assertCreated();
    
    $path = Path::where('name', 'title')->first();
    expect($path)->not->toBeNull();
    
    // Проверить, что constraints не созданы
    $constraints = PathRefConstraint::where('path_id', $path->id)->get();
    expect($constraints)->toHaveCount(0);
});

test('constraints возвращаются в API ответе при создании Path', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'author',
        'data_type' => 'ref',
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType1->id, $this->postType2->id],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.constraints.allowed_post_type_ids', [$this->postType1->id, $this->postType2->id]);
});

// Тесты обновления Path с constraints

test('constraints синхронизируются при обновлении Path с constraints', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
    ]);

    // Создать начальные constraints
    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType1->id]);
    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType2->id]);

    // Обновить constraints
    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType2->id, $this->postType3->id],
        ],
    ]);

    $response->assertOk();
    
    // Проверить, что старые constraints удалены, новые созданы
    $constraints = PathRefConstraint::where('path_id', $path->id)->get();
    expect($constraints)->toHaveCount(2);
    expect($constraints->pluck('allowed_post_type_id')->toArray())
        ->toContain($this->postType2->id, $this->postType3->id)
        ->not->toContain($this->postType1->id);
});

test('constraints не изменяются при частичном обновлении без constraints в запросе', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
        'name' => 'author',
    ]);

    // Создать начальные constraints
    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType1->id]);
    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType2->id]);

    // Обновить только name без constraints
    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'name' => 'updated_author',
    ]);

    $response->assertOk();
    
    // Проверить, что constraints не изменились
    $constraints = PathRefConstraint::where('path_id', $path->id)->get();
    expect($constraints)->toHaveCount(2);
    expect($constraints->pluck('allowed_post_type_id')->toArray())
        ->toContain($this->postType1->id, $this->postType2->id);
    
    // Проверить, что name обновился
    $path->refresh();
    expect($path->name)->toBe('updated_author');
});

test('constraints возвращаются в API ответе при обновлении Path', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
    ]);

    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType1->id, $this->postType2->id],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.constraints.allowed_post_type_ids', [$this->postType1->id, $this->postType2->id]);
});

// Тесты получения Path с constraints

test('constraints загружаются в методе index()', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
    ]);

    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType1->id]);
    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType2->id]);

    $response = $this->getJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths");

    $response->assertOk()
        ->assertJsonPath('data.0.constraints.allowed_post_type_ids', [$this->postType1->id, $this->postType2->id]);
});

test('constraints загружаются в методе show()', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
    ]);

    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType1->id]);
    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType2->id]);

    $response = $this->getJson("/api/v1/admin/paths/{$path->id}");

    $response->assertOk()
        ->assertJsonPath('data.constraints.allowed_post_type_ids', [$this->postType1->id, $this->postType2->id]);
});

// Тесты производительности (batch insert)

test('batch insert работает корректно при создании множества constraints', function () {
    $postTypes = PostType::factory()->count(10)->create();
    $postTypeIds = $postTypes->pluck('id')->toArray();

    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'author',
        'data_type' => 'ref',
        'constraints' => [
            'allowed_post_type_ids' => $postTypeIds,
        ],
    ]);

    $response->assertCreated();
    
    $path = Path::where('name', 'author')->first();
    $constraints = PathRefConstraint::where('path_id', $path->id)->get();
    
    expect($constraints)->toHaveCount(10);
    expect($constraints->pluck('allowed_post_type_id')->toArray())
        ->toEqualCanonicalizing($postTypeIds);
});

// Тесты синхронизации constraints

test('синхронизация constraints удаляет все старые и создает новые', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
    ]);

    // Создать начальные constraints
    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType1->id]);
    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType2->id]);
    PathRefConstraint::create(['path_id' => $path->id, 'allowed_post_type_id' => $this->postType3->id]);

    // Обновить constraints (полностью другие)
    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType1->id], // Только один
        ],
    ]);

    $response->assertOk();
    
    // Проверить, что остался только один constraint
    $constraints = PathRefConstraint::where('path_id', $path->id)->get();
    expect($constraints)->toHaveCount(1);
    expect($constraints->first()->allowed_post_type_id)->toBe($this->postType1->id);
});

