<?php

declare(strict_types=1);

use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->guard = new DynamicRouteGuard();
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

