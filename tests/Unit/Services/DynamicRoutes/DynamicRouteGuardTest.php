<?php

declare(strict_types=1);

use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->guard = new DynamicRouteGuard();
    Log::spy();
});

test('isMiddlewareAllowed(\'web\') возвращает true для разрешённого', function () {
    expect($this->guard->isMiddlewareAllowed('web'))->toBeTrue();
});

test('isMiddlewareAllowed(\'unknown\') возвращает false для неразрешённого', function () {
    expect($this->guard->isMiddlewareAllowed('unknown'))->toBeFalse();
    
    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Dynamic route: неразрешённый middleware', \Mockery::type('array'));
});

test('isMiddlewareAllowed(\'can:view,Entry\') возвращает true (параметризованный)', function () {
    expect($this->guard->isMiddlewareAllowed('can:view,Entry'))->toBeTrue()
        ->and($this->guard->isMiddlewareAllowed('can:create,PostType'))->toBeTrue();
});

test('isMiddlewareAllowed(\'throttle:60,1\') возвращает true (параметризованный)', function () {
    expect($this->guard->isMiddlewareAllowed('throttle:60,1'))->toBeTrue()
        ->and($this->guard->isMiddlewareAllowed('throttle:120,1'))->toBeTrue();
});

test('isControllerAllowed(\'App\\\\Http\\\\Controllers\\\\TestController\') проверяет по конфигу', function () {
    expect($this->guard->isControllerAllowed('App\\Http\\Controllers\\TestController'))->toBeTrue()
        ->and($this->guard->isControllerAllowed('App\\Http\\Controllers\\BlogController'))->toBeTrue();
});

test('isControllerAllowed() возвращает false для неразрешённого контроллера', function () {
    expect($this->guard->isControllerAllowed('App\\SomeOther\\Controller'))->toBeFalse();
    
    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Dynamic route: неразрешённый контроллер', \Mockery::type('array'));
});

test('sanitizeMiddleware([\'web\', \'unknown\']) возвращает только [\'web\']', function () {
    $result = $this->guard->sanitizeMiddleware(['web', 'unknown']);

    expect($result)->toBe(['web'])
        ->and($result)->not->toContain('unknown');
});

test('isPrefixReserved(\'api\') возвращает true', function () {
    expect($this->guard->isPrefixReserved('api'))->toBeTrue()
        ->and($this->guard->isPrefixReserved('admin'))->toBeTrue()
        ->and($this->guard->isPrefixReserved('sanctum'))->toBeTrue();
});

test('isPrefixReserved() возвращает true для префиксов, начинающихся с зарезервированного', function () {
    expect($this->guard->isPrefixReserved('api/v1'))->toBeTrue()
        ->and($this->guard->isPrefixReserved('admin/users'))->toBeTrue();
});

test('isPrefixReserved() возвращает false для разрешённых префиксов', function () {
    expect($this->guard->isPrefixReserved('blog'))->toBeFalse()
        ->and($this->guard->isPrefixReserved('about'))->toBeFalse()
        ->and($this->guard->isPrefixReserved('contact'))->toBeFalse();
});

