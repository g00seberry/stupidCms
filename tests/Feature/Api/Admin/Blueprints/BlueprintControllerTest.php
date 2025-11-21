<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\PostType;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class]);
});

test('можно создать blueprint через API', function () {
    $response = $this->postJson('/api/v1/admin/blueprints', [
        'name' => 'Article',
        'code' => 'article',
        'description' => 'Blog article structure',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'article')
        ->assertJsonPath('data.name', 'Article');

    $this->assertDatabaseHas('blueprints', ['code' => 'article']);
});

test('нельзя создать blueprint с дублирующимся code', function () {
    Blueprint::factory()->create(['code' => 'existing']);

    $response = $this->postJson('/api/v1/admin/blueprints', [
        'name' => 'Test',
        'code' => 'existing',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('можно добавить поле в blueprint', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->postJson("/api/v1/admin/blueprints/{$blueprint->id}/paths", [
        'name' => 'title',
        'data_type' => 'string',
        'is_required' => true,
        'is_indexed' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'title')
        ->assertJsonPath('data.full_path', 'title');

    $this->assertDatabaseHas('paths', [
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
    ]);
});

test('можно обновить blueprint', function () {
    $blueprint = Blueprint::factory()->create(['name' => 'Old Name']);

    $response = $this->putJson("/api/v1/admin/blueprints/{$blueprint->id}", [
        'name' => 'New Name',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    $this->assertDatabaseHas('blueprints', [
        'id' => $blueprint->id,
        'name' => 'New Name',
    ]);
});

test('нельзя удалить blueprint используемый в PostType', function () {
    $blueprint = Blueprint::factory()->create();
    PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    $response = $this->deleteJson("/api/v1/admin/blueprints/{$blueprint->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('detail', 'Невозможно удалить blueprint')
        ->assertJsonPath('meta.reasons', fn($reasons) => is_array($reasons) && count($reasons) > 0);

    $this->assertDatabaseHas('blueprints', ['id' => $blueprint->id]);
});

test('можно удалить неиспользуемый blueprint', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->deleteJson("/api/v1/admin/blueprints/{$blueprint->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('blueprints', ['id' => $blueprint->id]);
});

test('получение списка blueprints с пагинацией', function () {
    Blueprint::factory()->count(20)->create();

    $response = $this->getJson('/api/v1/admin/blueprints?per_page=10');

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

test('поиск blueprints по name/code', function () {
    Blueprint::factory()->create(['code' => 'article', 'name' => 'Article']);
    Blueprint::factory()->create(['code' => 'page', 'name' => 'Page']);

    $response = $this->getJson('/api/v1/admin/blueprints?search=article');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'article');
});

