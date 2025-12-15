<?php

declare(strict_types=1);

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\RouteNode;
use App\Models\User;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $this->withoutMiddleware();
});

test('Нельзя создать маршрут с запрещённым префиксом api', function () {
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

test('Нельзя создать маршрут с запрещённым префиксом admin', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => 'admin/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['uri']);
});

test('Нельзя использовать неразрешённый middleware', function () {
    $guard = app(DynamicRouteGuard::class);

    expect($guard->isMiddlewareAllowed('web'))->toBeTrue()
        ->and($guard->isMiddlewareAllowed('auth'))->toBeTrue()
        ->and($guard->isMiddlewareAllowed('can:view,Entry'))->toBeTrue()
        ->and($guard->isMiddlewareAllowed('throttle:60,1'))->toBeTrue()
        ->and($guard->isMiddlewareAllowed('dangerous:middleware'))->toBeFalse();
});

test('Нельзя использовать неразрешённый контроллер', function () {
    // Используем контроллер из другого namespace, не разрешённого в конфиге
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'Vendor\\Dangerous\\Controller@hack',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});



test('Публичный endpoint не отдаёт удалённые записи', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $entry->delete(); // Soft delete

    $routeNode = RouteNode::factory()->route()->create([
        'action_type' => RouteNodeActionType::ENTRY,
        'entry_id' => $entry->id,
        'uri' => '/deleted-page',
        'methods' => ['GET'],
        'enabled' => true,
    ]);

    $response = $this->getJson('/deleted-page');

    $response->assertStatus(404);
});

test('Публичный endpoint не отдаёт записи с будущей датой публикации', function () {
    $postType = PostType::factory()->create();
    $futureEntry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'status' => 'published',
        'published_at' => now()->addDays(7),
    ]);

    $routeNode = RouteNode::factory()->route()->create([
        'action_type' => RouteNodeActionType::ENTRY,
        'entry_id' => $futureEntry->id,
        'uri' => '/future-page',
        'methods' => ['GET'],
        'enabled' => true,
    ]);

    $response = $this->getJson('/future-page');

    $response->assertStatus(404);
});

test('Нельзя назначить entry_id без права view на Entry', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
    ]);

    // Пользователь без прав
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);
    $this->withoutMiddleware();

    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'entry',
        'uri' => '/test',
        'methods' => ['GET'],
        'entry_id' => $entry->id,
    ]);

    $response->assertStatus(403);
});

test('Нельзя использовать неразрешённый action_type', function () {
    // Проверяем, что валидация отклоняет невалидный action_type
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'invalid_type', // Невалидный тип
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['action_type']);

    // Проверяем, что enum не принимает невалидные значения при прямом использовании
    expect(fn () => RouteNodeActionType::from('invalid_type'))
        ->toThrow(ValueError::class);
});

test('SanitizeMiddleware фильтрует неразрешённые middleware', function () {
    $guard = app(DynamicRouteGuard::class);

    $middleware = [
        'web',
        'auth',
        'dangerous:middleware',
        'can:view,Entry',
        'throttle:60,1',
    ];

    $sanitized = $guard->sanitizeMiddleware($middleware);

    expect($sanitized)->toContain('web')
        ->toContain('auth')
        ->toContain('can:view,Entry')
        ->toContain('throttle:60,1')
        ->not->toContain('dangerous:middleware');
});

