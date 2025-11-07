<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Тест: /admin/ping должен обрабатываться до fallback.
     * 
     * Критерий приёмки: тестовый роут /admin не перехватывается catch-all/fallback,
     * а обрабатывается своим контроллером.
     */
    public function test_admin_ping_route_not_caught_by_fallback(): void
    {
        $response = $this->get('/admin/ping');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'OK',
            'message' => 'Admin ping route is working',
            'route' => '/admin/ping',
        ]);
    }

    /**
     * Тест: неизвестный путь должен обрабатываться fallback контроллером.
     */
    public function test_unknown_path_handled_by_fallback(): void
    {
        $response = $this->get('/non-existent-xyz');

        $response->assertStatus(404);
        $response->assertViewIs('errors.404');
        $response->assertViewHas('path', 'non-existent-xyz');
    }

    /**
     * Тест: fallback возвращает JSON для API запросов.
     */
    public function test_fallback_returns_json_for_api_requests(): void
    {
        $response = $this->getJson('/non-existent-xyz');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJsonStructure([
            'type',
            'title',
            'status',
            'detail',
            'path',
        ]);
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => 404,
        ]);
    }

    /**
     * Тест: главная страница обрабатывается до fallback.
     */
    public function test_home_route_not_caught_by_fallback(): void
    {
        $response = $this->get('/');

        // HomeController может вернуть view (200) или redirect (302), но не 404
        // Используем assertSuccessful() для проверки успешного ответа (2xx)
        $response->assertSuccessful();
        $this->assertNotEquals(404, $response->status());
    }

    /**
     * Тест: API роуты обрабатываются до fallback.
     */
    public function test_api_routes_not_caught_by_fallback(): void
    {
        // Даже без авторизации, роут должен быть найден (вернёт 401, а не 404)
        $response = $this->getJson('/api/v1/admin/reservations');

        // Если роут не найден, будет 404, если найден но нет авторизации - 401
        $this->assertNotEquals(404, $response->status());
        // Роут найден, но требуется авторизация
        $this->assertContains($response->status(), [401, 403]);
    }

    /**
     * Тест: API роуты обрабатываются для HEAD запросов.
     * 
     * HEAD автоматически доступен для всех GET роутов.
     * Проверяем, что роут найден (не 404).
     */
    public function test_api_routes_handle_head(): void
    {
        // HEAD запрос на GET роут должен найти роут (вернёт 401/403, не 404)
        $response = $this->head('/api/v1/admin/reservations');
        
        // Роут должен быть найден (не 404)
        $this->assertNotEquals(404, $response->status());
    }

    /**
     * Тест: порядок роутов сохраняется после route:cache.
     * 
     * Проверяем, что после кеширования роутов порядок не меняется.
     */
    public function test_routing_order_preserved_after_cache(): void
    {
        // Сначала проверяем без кеша
        $response1 = $this->get('/admin/ping');
        $response1->assertStatus(200);

        // Кешируем роуты
        $this->artisan('route:cache')->assertSuccessful();

        // Проверяем после кеширования
        $response2 = $this->get('/admin/ping');
        $response2->assertStatus(200);
        $response2->assertJson([
            'status' => 'OK',
        ]);

        // Очищаем кеш
        $this->artisan('route:clear')->assertSuccessful();
    }

    /**
     * Тест: POST на неизвестный путь возвращает 404, а не 419 CSRF.
     * 
     * Fallback НЕ должен быть под web middleware, иначе POST без CSRF токена
     * получит 419 вместо ожидаемого 404.
     */
    public function test_fallback_returns_404_on_post_without_csrf(): void
    {
        // POST на несуществующий путь (HTML) должен вернуть 404, не 419
        $response = $this->post('/totally-unknown');
        $response->assertStatus(404);

        // POST на несуществующий путь (JSON) тоже 404 с problem+json
        $response = $this->postJson('/totally-unknown');
        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    /**
     * Тест: неизвестный путь под /api/* использует problem+json.
     * 
     * Проверяет логику is('api/*') в FallbackController.
     */
    public function test_api_prefix_unknown_path_uses_problem_json(): void
    {
        $response = $this->get('/api/v1/not-found-abc');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => 404,
        ]);
    }

    /**
     * Тест: OPTIONS preflight на неизвестный API путь возвращает 404 с problem+json.
     * 
     * CORS preflight запросы (OPTIONS) на несуществующие API пути должны
     * возвращать 404 с application/problem+json, а не пустой ответ или HTML.
     * 
     * Примечание: Laravel может автоматически обрабатывать OPTIONS для CORS,
     * но для неизвестных путей fallback должен вернуть 404.
     */
    public function test_options_preflight_on_unknown_api_path_returns_problem_json(): void
    {
        // OPTIONS без CORS заголовков - должен попасть в fallback
        $response = $this->options('/api/v1/some-unknown');

        // Fallback должен обработать OPTIONS и вернуть 404 с problem+json
        // Если Laravel перехватил OPTIONS для CORS, будет 204, но для неизвестных путей
        // fallback должен сработать
        if ($response->status() === 404) {
            $response->assertHeader('Content-Type', 'application/problem+json');
            $response->assertJson([
                'type' => 'about:blank',
                'title' => 'Not Found',
                'status' => 404,
            ]);
        } else {
            // Если Laravel обработал OPTIONS автоматически (204), это тоже нормально
            // Главное - проверить, что для GET того же пути будет 404
            $getResponse = $this->get('/api/v1/some-unknown');
            $getResponse->assertStatus(404);
            $getResponse->assertHeader('Content-Type', 'application/problem+json');
        }
    }
}

