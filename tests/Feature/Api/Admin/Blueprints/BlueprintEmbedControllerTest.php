<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class]);
});

test('можно создать встраивание', function () {
    $host = Blueprint::factory()->create(['code' => 'company']);
    $embedded = Blueprint::factory()->create(['code' => 'address']);

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'street', 'full_path' => 'street']);

    $response = $this->postJson("/api/v1/admin/blueprints/{$host->id}/embeds", [
        'embedded_blueprint_id' => $embedded->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.blueprint_id', $host->id)
        ->assertJsonPath('data.embedded_blueprint_id', $embedded->id);

    // Проверить материализацию
    $copiedPaths = Path::where('blueprint_id', $host->id)
        ->where('source_blueprint_id', $embedded->id)
        ->get();

    expect($copiedPaths)->toHaveCount(1)
        ->and($copiedPaths->first()->name)->toBe('street');
});

test('можно создать встраивание под host_path', function () {
    $host = Blueprint::factory()->create(['code' => 'company']);
    $embedded = Blueprint::factory()->create(['code' => 'address']);

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'street', 'full_path' => 'street']);

    $office = Path::factory()->create([
        'blueprint_id' => $host->id,
        'name' => 'office',
        'full_path' => 'office',
        'data_type' => 'json',
    ]);

    $response = $this->postJson("/api/v1/admin/blueprints/{$host->id}/embeds", [
        'embedded_blueprint_id' => $embedded->id,
        'host_path_id' => $office->id,
    ]);

    $response->assertCreated();

    // Проверить full_path копии
    $copiedPath = Path::where('blueprint_id', $host->id)
        ->where('full_path', 'office.street')
        ->first();

    expect($copiedPath)->not->toBeNull()
        ->and($copiedPath->parent_id)->toBe($office->id);
});

test('нельзя создать цикл через API', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    Path::factory()->create(['blueprint_id' => $b->id, 'name' => 'field1', 'full_path' => 'field1']);

    // A → B (ok)
    $this->postJson("/api/v1/admin/blueprints/{$a->id}/embeds", [
        'embedded_blueprint_id' => $b->id,
    ])->assertCreated();

    // B → A (цикл)
    $response = $this->postJson("/api/v1/admin/blueprints/{$b->id}/embeds", [
        'embedded_blueprint_id' => $a->id,
    ]);

    // Циклическая зависимость обрабатывается через систему управления ошибками и возвращает 409 (CONFLICT)
    $response->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');
});

test('можно удалить встраивание', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'field1', 'full_path' => 'field1']);

    $createResponse = $this->postJson("/api/v1/admin/blueprints/{$host->id}/embeds", [
        'embedded_blueprint_id' => $embedded->id,
    ]);

    $embedId = $createResponse->json('data.id');

    // Удалить
    $response = $this->deleteJson("/api/v1/admin/embeds/{$embedId}");

    $response->assertOk();

    // Проверить, что копии удалены
    $copiesCount = Path::where('blueprint_id', $host->id)
        ->where('source_blueprint_id', $embedded->id)
        ->count();

    expect($copiesCount)->toBe(0);
});

test('получение списка встраиваний blueprint', function () {
    $host = Blueprint::factory()->create();
    $embedded1 = Blueprint::factory()->create(['code' => 'embedded1']);
    $embedded2 = Blueprint::factory()->create(['code' => 'embedded2']);

    Path::factory()->create(['blueprint_id' => $embedded1->id, 'name' => 'f1', 'full_path' => 'f1']);
    Path::factory()->create(['blueprint_id' => $embedded2->id, 'name' => 'f2', 'full_path' => 'f2']);

    $this->postJson("/api/v1/admin/blueprints/{$host->id}/embeds", ['embedded_blueprint_id' => $embedded1->id]);
    $this->postJson("/api/v1/admin/blueprints/{$host->id}/embeds", ['embedded_blueprint_id' => $embedded2->id]);

    $response = $this->getJson("/api/v1/admin/blueprints/{$host->id}/embeds");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('нельзя создать дубликат встраивания в одно место', function () {
    $host = Blueprint::factory()->create(['code' => 'host']);
    $embedded = Blueprint::factory()->create(['code' => 'embedded']);

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'field1', 'full_path' => 'field1']);

    // Первое встраивание
    $this->postJson("/api/v1/admin/blueprints/{$host->id}/embeds", [
        'embedded_blueprint_id' => $embedded->id,
    ])->assertCreated();

    // Попытка создать дубликат
    $response = $this->postJson("/api/v1/admin/blueprints/{$host->id}/embeds", [
        'embedded_blueprint_id' => $embedded->id,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT')
        ->assertJsonPath('detail', "Blueprint 'embedded' уже встроен в 'host' в корень.");
});

test('нельзя создать дубликат встраивания под одно host_path', function () {
    $host = Blueprint::factory()->create(['code' => 'host']);
    $embedded = Blueprint::factory()->create(['code' => 'embedded']);

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'field1', 'full_path' => 'field1']);

    $hostPath = Path::factory()->create([
        'blueprint_id' => $host->id,
        'name' => 'office',
        'full_path' => 'office',
        'data_type' => 'json',
    ]);

    // Первое встраивание под host_path
    $this->postJson("/api/v1/admin/blueprints/{$host->id}/embeds", [
        'embedded_blueprint_id' => $embedded->id,
        'host_path_id' => $hostPath->id,
    ])->assertCreated();

    // Попытка создать дубликат под тем же host_path
    $response = $this->postJson("/api/v1/admin/blueprints/{$host->id}/embeds", [
        'embedded_blueprint_id' => $embedded->id,
        'host_path_id' => $hostPath->id,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT')
        ->assertJsonPath('detail', "Blueprint 'embedded' уже встроен в 'host' под полем 'office'.");
});

