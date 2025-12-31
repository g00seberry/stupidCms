<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Path;
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

// StorePathRequest тесты

test('можно создать Path с валидными constraints для ref-поля', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'author',
        'data_type' => 'ref',
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType1->id, $this->postType2->id],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'author')
        ->assertJsonPath('data.data_type', 'ref');
});

test('нельзя создать Path с constraints для не ref-поля', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'title',
        'data_type' => 'string',
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType1->id],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('constraints');
});

test('нельзя создать Path с несуществующими PostType в constraints', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'author',
        'data_type' => 'ref',
        'constraints' => [
            'allowed_post_type_ids' => [99999, 99998],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('constraints.allowed_post_type_ids.0')
        ->assertJsonValidationErrors('constraints.allowed_post_type_ids.1');
});

test('нельзя создать Path с дубликатами PostType в constraints', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'author',
        'data_type' => 'ref',
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType1->id, $this->postType1->id],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('constraints.allowed_post_type_ids.0')
        ->assertJsonValidationErrors('constraints.allowed_post_type_ids.1');
});

test('можно создать Path без constraints', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'title',
        'data_type' => 'string',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'title');
});

test('нельзя создать Path с data_type=ref без constraints.allowed_post_type_ids', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'author',
        'data_type' => 'ref',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('constraints.allowed_post_type_ids');
});

test('нельзя создать Path с data_type=ref с пустым массивом constraints.allowed_post_type_ids', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'author',
        'data_type' => 'ref',
        'constraints' => [
            'allowed_post_type_ids' => [],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('constraints.allowed_post_type_ids');
});

test('constraints.allowed_post_type_ids должен быть массивом', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'author',
        'data_type' => 'ref',
        'constraints' => [
            'allowed_post_type_ids' => 'not-an-array',
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('constraints.allowed_post_type_ids');
});

test('элементы constraints.allowed_post_type_ids должны быть целыми числами', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'author',
        'data_type' => 'ref',
        'constraints' => [
            'allowed_post_type_ids' => ['string', 123],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('constraints.allowed_post_type_ids.0');
});

// UpdatePathRequest тесты

test('можно обновить Path с валидными constraints для ref-поля', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
    ]);

    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType1->id, $this->postType2->id],
        ],
    ]);

    $response->assertOk();
});

test('можно обновить только constraints без изменения других полей', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
        'name' => 'author',
    ]);

    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType1->id],
        ],
    ]);

    $response->assertOk();
    $path->refresh();
    expect($path->name)->toBe('author');
});

test('нельзя обновить Path с constraints для не ref-поля', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'string',
    ]);

    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'constraints' => [
            'allowed_post_type_ids' => [$this->postType1->id],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('constraints');
});

test('можно обновить Path без constraints (частичное обновление)', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
    ]);

    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'name' => 'updated_name',
    ]);

    $response->assertOk();
    $path->refresh();
    expect($path->name)->toBe('updated_name');
});

test('нельзя обновить Path с несуществующими PostType в constraints', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
    ]);

    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'constraints' => [
            'allowed_post_type_ids' => [99999],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('constraints.allowed_post_type_ids.0');
});

test('нельзя обновить Path с пустым массивом constraints.allowed_post_type_ids', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'ref',
    ]);

    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'constraints' => [
            'allowed_post_type_ids' => [],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('constraints.allowed_post_type_ids');
});

