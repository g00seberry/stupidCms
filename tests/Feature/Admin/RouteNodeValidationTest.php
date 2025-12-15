<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\PostType;
use App\Models\RouteNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $this->withoutMiddleware();
});

test('POST /api/v1/admin/routes с запрещённым префиксом api → 422', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => 'api/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['uri']);
});

test('POST /api/v1/admin/routes с невалидным kind → 422', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'invalid',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['kind']);
});

test('POST /api/v1/admin/routes с несуществующим entry_id → 422', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'entry',
        'uri' => '/test',
        'methods' => ['GET'],
        'entry_id' => 99999,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['entry_id']);
});

test('POST /api/v1/admin/routes с корректными данными → 201', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response->assertStatus(201);
});


test('POST /api/v1/admin/routes с запрещённым префиксом prefix → 422', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'group',
        'action_type' => 'controller',
        'prefix' => 'api',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['prefix']);
});

test('POST /api/v1/admin/routes с невалидным HTTP методом → 422', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['INVALID'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['methods.0']);
});

test('POST /api/v1/admin/routes с корректным форматом view: → 201', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'view:pages.about',
    ]);

    $response->assertStatus(201);
});

test('POST /api/v1/admin/routes с корректным форматом redirect: → 201', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'redirect:/new-page:301',
    ]);

    $response->assertStatus(201);
});

test('POST /api/v1/admin/routes с корректным форматом Controller@method → 201', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response->assertStatus(201);
});

test('POST /api/v1/admin/routes с корректным форматом Invokable Controller → 201', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController',
    ]);

    $response->assertStatus(201);
});

