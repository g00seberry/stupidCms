<?php

namespace Tests\Feature;

use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Domain\Routing\Exceptions\InvalidPathException;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use App\Domain\Routing\PathReservationService;
use App\Models\ReservedRoute;
use Tests\Support\FeatureTestCase;

class PathReservationServiceTest extends FeatureTestCase
{
    private PathReservationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::forget('reserved:first-segments');
        $this->service = app(PathReservationService::class);
    }

    public function test_reserve_path_success(): void
    {
        $this->service->reservePath('/feed.xml', 'system:feeds', 'RSS feed');

        $this->assertDatabaseHas('reserved_routes', [
            'path' => '/feed.xml',
            'kind' => 'path',
            'source' => 'system:feeds',
        ]);
    }

    public function test_reserve_path_duplicate_throws_exception(): void
    {
        $this->service->reservePath('/feed.xml', 'system:feeds');

        $this->expectException(PathAlreadyReservedException::class);
        $this->expectExceptionMessage("Path '/feed.xml' is already reserved");

        $this->service->reservePath('/feed.xml', 'plugin:shop');
    }

    public function test_reserve_path_duplicate_from_same_source_throws_exception(): void
    {
        $this->service->reservePath('/feed.xml', 'system:feeds');

        $this->expectException(PathAlreadyReservedException::class);

        // Даже тот же источник не может зарезервировать дважды
        $this->service->reservePath('/feed.xml', 'system:feeds');
    }

    public function test_reserve_path_normalizes_case(): void
    {
        $this->service->reservePath('/Feed.xml', 'system:feeds');
        
        // Второй вызов с другим регистром должен упасть
        $this->expectException(PathAlreadyReservedException::class);
        $this->service->reservePath('/FEED.XML', 'plugin:shop');
    }

    public function test_reserve_path_static_from_config_throws_exception(): void
    {
        // Путь 'admin' из конфига должен быть заблокирован
        $this->expectException(PathAlreadyReservedException::class);
        $this->expectExceptionMessage("static:config");

        $this->service->reservePath('/admin', 'plugin:shop');
    }

    public function test_release_path_success(): void
    {
        $this->service->reservePath('/feed.xml', 'system:feeds');
        $this->service->releasePath('/feed.xml', 'system:feeds');

        $this->assertDatabaseMissing('reserved_routes', [
            'path' => '/feed.xml',
        ]);
    }

    public function test_release_path_wrong_source_throws_exception(): void
    {
        $this->service->reservePath('/shop', 'plugin:shop');

        $this->expectException(ForbiddenReservationRelease::class);
        $this->expectExceptionMessage("Cannot release path '/shop'");

        $this->service->releasePath('/shop', 'plugin:other');
    }

    public function test_release_by_source(): void
    {
        $this->service->reservePath('/shop', 'plugin:shop');
        $this->service->reservePath('/shop/cart', 'plugin:shop');
        $this->service->reservePath('/blog', 'plugin:blog');

        $count = $this->service->releaseBySource('plugin:shop');

        $this->assertEquals(2, $count);
        $this->assertDatabaseMissing('reserved_routes', [
            'path' => '/shop',
        ]);
        $this->assertDatabaseMissing('reserved_routes', [
            'path' => '/shop/cart',
        ]);
        $this->assertDatabaseHas('reserved_routes', [
            'path' => '/blog',
        ]);
    }

    public function test_is_reserved_returns_true(): void
    {
        $this->service->reservePath('/feed.xml', 'system:feeds');

        $this->assertTrue($this->service->isReserved('/feed.xml'));
        $this->assertTrue($this->service->isReserved('/Feed.xml')); // case-insensitive
    }

    public function test_is_reserved_returns_false(): void
    {
        $this->assertFalse($this->service->isReserved('/not-reserved'));
    }

    public function test_is_reserved_static_paths(): void
    {
        // Путь из конфига должен быть зарезервирован
        $this->assertTrue($this->service->isReserved('/admin'));
    }

    public function test_owner_of_returns_source(): void
    {
        $this->service->reservePath('/feed.xml', 'system:feeds');

        $this->assertEquals('system:feeds', $this->service->ownerOf('/feed.xml'));
    }

    public function test_owner_of_returns_null(): void
    {
        $this->assertNull($this->service->ownerOf('/not-reserved'));
    }

    public function test_owner_of_static_paths(): void
    {
        $this->assertEquals('static:config', $this->service->ownerOf('/admin'));
    }

    public function test_invalid_path_throws_exception(): void
    {
        $this->expectException(InvalidPathException::class);

        $this->service->reservePath('', 'system:core');
    }

    public function test_invalid_path_hash_throws_exception(): void
    {
        $this->expectException(InvalidPathException::class);

        $this->service->reservePath('#', 'system:core');
    }

    public function test_path_normalization_removes_trailing_slash(): void
    {
        $this->service->reservePath('/test/', 'system:core');

        $this->assertDatabaseHas('reserved_routes', [
            'path' => '/test',
        ]);
    }

    public function test_path_normalization_preserves_root(): void
    {
        $this->service->reservePath('/', 'system:root');

        $this->assertDatabaseHas('reserved_routes', [
            'path' => '/',
        ]);
    }

    public function test_path_normalization_removes_query_and_fragment(): void
    {
        $this->service->reservePath('/test?foo=bar#section', 'system:core');

        $this->assertDatabaseHas('reserved_routes', [
            'path' => '/test',
        ]);
    }

    public function test_path_normalization_removes_duplicate_slashes(): void
    {
        $this->service->reservePath('/test//path', 'system:core');

        $this->assertDatabaseHas('reserved_routes', [
            'path' => '/test/path',
        ]);
    }

    public function test_path_normalization_removes_relative_segments(): void
    {
        $this->service->reservePath('/test/./path', 'system:core');

        $this->assertDatabaseHas('reserved_routes', [
            'path' => '/test/path',
        ]);
    }

    public function test_model_mutator_normalizes_path(): void
    {
        // Прямое создание модели должно нормализовать путь через мутатор
        $reservation = ReservedRoute::create([
            'path' => '/Test//Path',
            'kind' => 'path',
            'source' => 'system:core',
        ]);

        $this->assertEquals('/test/path', $reservation->path);
    }
}

