<?php

namespace Tests\Unit;

use App\Models\ReservedRoute;
use App\Support\ReservedRoutes\ReservedRouteRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReservedRouteRegistryTest extends TestCase
{
    use RefreshDatabase;

    private ReservedRouteRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        
        $config = [
            'reserved_routes' => [
                'paths' => ['admin'],
                'prefixes' => ['admin', 'api'],
            ],
        ];
        
        $this->registry = new ReservedRouteRegistry(Cache::store(), $config);
        $this->registry->clearCache(); // Очищаем кэш перед каждым тестом
    }

    public function test_is_reserved_slug_returns_true_for_admin(): void
    {
        $this->assertTrue($this->registry->isReservedSlug('admin'));
    }

    public function test_is_reserved_slug_returns_true_for_api(): void
    {
        $this->assertTrue($this->registry->isReservedSlug('api'));
    }

    public function test_is_reserved_slug_case_insensitive(): void
    {
        $this->assertTrue($this->registry->isReservedSlug('Admin'));
        $this->assertTrue($this->registry->isReservedSlug('ADMIN'));
        $this->assertTrue($this->registry->isReservedSlug('API'));
    }

    public function test_is_reserved_slug_returns_false_for_normal_slug(): void
    {
        $this->assertFalse($this->registry->isReservedSlug('about'));
        $this->assertFalse($this->registry->isReservedSlug('contact'));
    }

    public function test_is_reserved_slug_returns_false_for_api_v1(): void
    {
        // api-v1 не зарезервирован, так как slug - один сегмент, а префикс проверяется по точному совпадению
        $this->assertFalse($this->registry->isReservedSlug('api-v1'));
    }

    public function test_is_reserved_path_returns_true_for_admin(): void
    {
        $this->assertTrue($this->registry->isReservedPath('admin'));
    }

    public function test_is_reserved_path_case_insensitive(): void
    {
        $this->assertTrue($this->registry->isReservedPath('Admin'));
    }

    public function test_is_reserved_prefix_returns_true_for_api(): void
    {
        $this->assertTrue($this->registry->isReservedPrefix('api'));
    }

    public function test_loads_from_database(): void
    {
        // Создаём запись в БД
        ReservedRoute::create([
            'path' => 'test',
            'kind' => 'path',
            'source' => 'core',
        ]);

        $this->registry->clearCache();
        
        $this->assertTrue($this->registry->isReservedSlug('test'));
    }

    public function test_merges_config_and_database(): void
    {
        // Добавляем в БД
        ReservedRoute::create([
            'path' => 'custom',
            'kind' => 'prefix',
            'source' => 'plugin',
        ]);

        $this->registry->clearCache();
        
        // Проверяем, что и из конфига, и из БД работают
        $this->assertTrue($this->registry->isReservedSlug('admin')); // из конфига
        $this->assertTrue($this->registry->isReservedSlug('custom')); // из БД
    }

    public function test_normalizes_paths(): void
    {
        // Проверяем нормализацию (trim слэшей, пробелов)
        $this->assertTrue($this->registry->isReservedSlug('/admin'));
        $this->assertTrue($this->registry->isReservedSlug('admin/'));
        $this->assertTrue($this->registry->isReservedSlug(' admin '));
    }

    public function test_all_returns_merged_routes(): void
    {
        $all = $this->registry->all();
        
        $this->assertArrayHasKey('paths', $all);
        $this->assertArrayHasKey('prefixes', $all);
        $this->assertContains('admin', $all['paths']);
        $this->assertContains('admin', $all['prefixes']);
        $this->assertContains('api', $all['prefixes']);
    }

    public function test_cache_works(): void
    {
        // Первый вызов загружает в кэш
        $this->registry->all();
        
        // Добавляем в БД после кэширования
        ReservedRoute::create([
            'path' => 'cached',
            'kind' => 'path',
            'source' => 'core',
        ]);
        
        // Должно быть закэшировано, поэтому не видит новую запись
        $this->assertFalse($this->registry->isReservedSlug('cached'));
        
        // После очистки кэша должно увидеть
        $this->registry->clearCache();
        $this->assertTrue($this->registry->isReservedSlug('cached'));
    }
}

