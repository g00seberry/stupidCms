<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin\Paths;

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\PathMediaConstraint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class]);

    $this->blueprint = Blueprint::factory()->create();
});

// Тесты создания Path с constraints

test('constraints сохраняются в БД при создании Path с media-полем', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'avatar',
        'data_type' => 'media',
        'constraints' => [
            'allowed_mimes' => ['image/jpeg', 'image/png'],
        ],
    ]);

    $response->assertCreated();
    
    $path = Path::where('name', 'avatar')->first();
    expect($path)->not->toBeNull();
    
    // Проверить, что constraints созданы в БД
    $constraints = PathMediaConstraint::where('path_id', $path->id)->get();
    expect($constraints)->toHaveCount(2);
    expect($constraints->pluck('allowed_mime')->toArray())
        ->toContain('image/jpeg', 'image/png');
});

test('constraints возвращаются в API ответе при создании Path', function () {
    $response = $this->postJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths", [
        'name' => 'avatar',
        'data_type' => 'media',
        'constraints' => [
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/gif'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.constraints.allowed_mimes', ['image/jpeg', 'image/png', 'image/gif']);
});

// Тесты обновления Path с constraints

test('constraints синхронизируются при обновлении Path с constraints', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'media',
    ]);

    // Создать начальные constraints
    PathMediaConstraint::create(['path_id' => $path->id, 'allowed_mime' => 'image/jpeg']);
    PathMediaConstraint::create(['path_id' => $path->id, 'allowed_mime' => 'image/png']);

    // Обновить constraints
    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'constraints' => [
            'allowed_mimes' => ['image/png', 'image/gif'],
        ],
    ]);

    $response->assertOk();
    
    // Проверить, что старые constraints удалены, новые созданы
    $constraints = PathMediaConstraint::where('path_id', $path->id)->get();
    expect($constraints)->toHaveCount(2);
    expect($constraints->pluck('allowed_mime')->toArray())
        ->toContain('image/png', 'image/gif')
        ->not->toContain('image/jpeg');
});

test('constraints возвращаются в API ответе при обновлении Path', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'media',
    ]);

    $response = $this->putJson("/api/v1/admin/paths/{$path->id}", [
        'constraints' => [
            'allowed_mimes' => ['image/jpeg', 'image/png'],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.constraints.allowed_mimes', ['image/jpeg', 'image/png']);
});

// Тесты получения Path с constraints

test('constraints загружаются в методе index()', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'media',
    ]);

    PathMediaConstraint::create(['path_id' => $path->id, 'allowed_mime' => 'image/jpeg']);
    PathMediaConstraint::create(['path_id' => $path->id, 'allowed_mime' => 'image/png']);

    $response = $this->getJson("/api/v1/admin/blueprints/{$this->blueprint->id}/paths");

    $response->assertOk()
        ->assertJsonPath('data.0.constraints.allowed_mimes', ['image/jpeg', 'image/png']);
});

test('constraints загружаются в методе show()', function () {
    $path = Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'data_type' => 'media',
    ]);

    PathMediaConstraint::create(['path_id' => $path->id, 'allowed_mime' => 'image/jpeg']);
    PathMediaConstraint::create(['path_id' => $path->id, 'allowed_mime' => 'image/png']);

    $response = $this->getJson("/api/v1/admin/paths/{$path->id}");

    $response->assertOk()
        ->assertJsonPath('data.constraints.allowed_mimes', ['image/jpeg', 'image/png']);
});

