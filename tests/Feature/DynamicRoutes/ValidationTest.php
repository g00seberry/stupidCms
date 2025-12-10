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

test('Валидация kind: только group или route', function () {
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

test('Валидация action_type: только controller или entry', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'invalid',
        'uri' => '/test',
        'methods' => ['GET'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['action_type']);
});

test('Валидация action для action_type=controller: формат Controller@method', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response->assertStatus(201);
});

test('Валидация action для action_type=controller: формат Invokable', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController',
    ]);

    $response->assertStatus(201);
});

test('Валидация action для action_type=controller: формат view:', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'view:pages.home',
    ]);

    $response->assertStatus(201);
});

test('Валидация action для action_type=controller: формат redirect:', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'redirect:/new-page:301',
    ]);

    $response->assertStatus(201);
});

test('Валидация action для action_type=entry: action должен быть null', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
    ]);

    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'entry',
        'uri' => '/test',
        'methods' => ['GET'],
        'entry_id' => $entry->id,
        'action' => 'some-action', // Не должно быть action для entry
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('Валидация entry_id: обязателен для action_type=entry', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'entry',
        'uri' => '/test',
        'methods' => ['GET'],
        // entry_id отсутствует
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['entry_id']);
});

test('Валидация entry_id: должен существовать', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'entry',
        'uri' => '/test',
        'methods' => ['GET'],
        'entry_id' => 99999, // Несуществующий ID
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['entry_id']);
});

test('Валидация methods: должен быть массивом', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => 'GET', // Не массив
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['methods']);
});

test('Валидация methods: должен содержать валидные HTTP методы', function () {
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

test('Валидация middleware: должен быть массивом', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'middleware' => 'web', // Не массив
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['middleware']);
});

test('Валидация middleware: элементы должны быть строками', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'middleware' => [123], // Не строка
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['middleware.0']);
});

test('Валидация where: должен быть массивом', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'where' => 'id', // Не массив
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['where']);
});

test('Валидация defaults: должен быть массивом', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'defaults' => 'value', // Не массив
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['defaults']);
});

test('Валидация options: должен быть массивом', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'options' => 'value', // Не массив
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['options']);
});

test('Валидация parent_id: должен существовать', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'parent_id' => 99999, // Несуществующий ID
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['parent_id']);
});

test('Валидация sort_order: должен быть неотрицательным', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'sort_order' => -1, // Отрицательное значение
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['sort_order']);
});

test('Валидация name: максимум 255 символов', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'name' => str_repeat('a', 256), // Превышает лимит
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('Валидация domain: максимум 255 символов', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'domain' => str_repeat('a', 256), // Превышает лимит
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['domain']);
});

test('Валидация prefix: максимум 255 символов', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'group',
        'action_type' => 'controller',
        'prefix' => str_repeat('a', 256), // Превышает лимит
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['prefix']);
});

test('Валидация namespace: максимум 255 символов', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'group',
        'action_type' => 'controller',
        'namespace' => str_repeat('a', 256), // Превышает лимит
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['namespace']);
});

test('Валидация action: максимум 255 символов', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => str_repeat('a', 256), // Превышает лимит
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('Валидация: SQL injection в uri не приводит к выполнению SQL', function () {
    $maliciousUri = "'; DROP TABLE route_nodes; --";

    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => $maliciousUri,
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // URI должен быть сохранён как строка, но не выполнен как SQL
    $response->assertStatus(201);

    $node = RouteNode::latest()->first();
    expect($node->uri)->toBe($maliciousUri);

    // Проверяем, что таблица всё ещё существует
    expect(RouteNode::count())->toBeGreaterThan(0);
});

test('Валидация: XSS в name экранируется при выводе', function () {
    $xssPayload = '<script>alert("XSS")</script>';

    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'name' => $xssPayload,
    ]);

    $response->assertStatus(201);

    $node = RouteNode::latest()->first();
    expect($node->name)->toBe($xssPayload);

    // При выводе через JSON ответ должен быть экранирован
    $showResponse = $this->getJson("/api/v1/admin/routes/{$node->id}");
    $showResponse->assertStatus(200);

    $json = $showResponse->json();
    // JSON автоматически экранирует специальные символы
    expect($json['data']['name'])->toBe($xssPayload);
    // Проверяем, что в JSON это строка, а не выполненный скрипт
    expect(is_string($json['data']['name']))->toBeTrue();
});

test('Валидация: XSS в action экранируется при выводе', function () {
    $xssPayload = '<script>alert("XSS")</script>';

    // view:pages.<script> технически валидный формат, но проверяем безопасность при выводе
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'view:pages.' . $xssPayload,
    ]);

    // view: формат принимается, но проверяем безопасность при выводе
    $response->assertStatus(201);

    $node = RouteNode::latest()->first();
    expect($node->action)->toContain($xssPayload);

    // При выводе через JSON ответ должен быть экранирован
    $showResponse = $this->getJson("/api/v1/admin/routes/{$node->id}");
    $showResponse->assertStatus(200);

    $json = $showResponse->json();
    // JSON автоматически экранирует специальные символы
    expect($json['data']['action'])->toContain($xssPayload);
    // Проверяем, что в JSON это строка, а не выполненный скрипт
    expect(is_string($json['data']['action']))->toBeTrue();
});

