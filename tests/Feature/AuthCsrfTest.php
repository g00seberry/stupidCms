<?php

namespace Tests\Feature;

use App\Support\Errors\ErrorCode;
use Tests\Support\FeatureTestCase;

class AuthCsrfTest extends FeatureTestCase
{
    private function getCsrfCookieName(): string
    {
        return config('security.csrf.cookie_name');
    }

    public function test_post_without_csrf_token_returns_419_with_new_cookie(): void
    {
        // Попытка POST запроса без CSRF токена
        // Используем админский endpoint который требует CSRF
        $user = \App\Models\User::factory()->create();
        
        // Получаем JWT access token
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $accessToken = $jwtService->issueAccessToken($user->id, ['scp' => ['admin'], 'aud' => 'admin']);
        
        // Пытаемся сделать POST запрос без CSRF токена
        $response = $this->postJsonWithCookies('/api/v1/admin/entries', [
            'post_type_id' => 1,
            'title' => 'Test',
            'status' => 'draft',
        ], [
            config('jwt.cookies.access') => $accessToken,
        ]);

        $response->assertStatus(419);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::CSRF_TOKEN_MISMATCH, [
            'detail' => 'CSRF token mismatch.',
        ]);
        
        // Проверка, что при 419 выдается новый CSRF cookie
        $cookieName = $this->getCsrfCookieName();
        $this->assertTrue($response->headers->has('Set-Cookie'), 'New CSRF cookie should be issued on 419');
        $setCookieHeader = $response->headers->get('Set-Cookie');
        $this->assertStringContainsString($cookieName . '=', $setCookieHeader, 'New CSRF cookie should be present');

        // Проверка Vary заголовков для кэширования
        $varyHeader = $response->headers->get('Vary');
        $this->assertNotNull($varyHeader, 'Vary header should be present on CSRF mismatch');
        $this->assertStringContainsString('Origin', $varyHeader, 'Vary header should include Origin');
        $this->assertStringContainsString('Cookie', $varyHeader, 'Vary header should include Cookie');
    }

    public function test_login_endpoint_excluded_from_csrf_check(): void
    {
        // Login endpoint должен работать без CSRF токена
        $user = \App\Models\User::factory()->create(['password' => bcrypt('secretPass123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secretPass123',
        ]);

        // Login должен работать без CSRF (excluded by route name)
        $response->assertOk();
    }

    public function test_refresh_endpoint_excluded_from_csrf_check(): void
    {
        // Refresh endpoint должен работать без CSRF токена
        // Но нужен валидный refresh token
        // Для этого теста просто проверяем, что без CSRF не возвращается 419
        // (будет 401 из-за отсутствия refresh token, но не 419 из-за CSRF)
        
        $response = $this->postJson('/api/v1/auth/refresh');

        // Должен вернуть 401 (нет refresh token), но НЕ 419 (CSRF проверка пропущена)
        $this->assertNotEquals(419, $response->status(), 'Refresh endpoint should not require CSRF token');
        $response->assertStatus(401);
    }

    public function test_logout_endpoint_excluded_from_csrf_check(): void
    {
        // Logout endpoint должен работать без CSRF токена (но требует JWT auth)
        // Без JWT должен вернуть 401, но НЕ 419
        
        $response = $this->postJson('/api/v1/auth/logout');

        // Должен вернуть 401 (нет JWT), но НЕ 419 (CSRF проверка пропущена)
        $this->assertNotEquals(419, $response->status(), 'Logout endpoint should not require CSRF token');
        $response->assertStatus(401);
    }

    public function test_get_request_bypasses_csrf_check(): void
    {
        // GET запросы не требуют CSRF токена
        $response = $this->getJson('/api/v1/search');

        // Не должен возвращать 419 (может вернуть другие ошибки, но не CSRF)
        $this->assertNotEquals(419, $response->status(), 'GET requests should bypass CSRF check');
    }

    public function test_head_request_bypasses_csrf_check(): void
    {
        // HEAD запросы не требуют CSRF токена
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->call('HEAD', '/api/v1/search');

        // Не должен возвращать 419
        $this->assertNotEquals(419, $response->status(), 'HEAD requests should bypass CSRF check');
    }

    public function test_options_preflight_request_bypasses_csrf_check(): void
    {
        // OPTIONS preflight запросы не требуют CSRF токена
        $user = \App\Models\User::factory()->create();
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $accessToken = $jwtService->issueAccessToken($user->id, ['scp' => ['admin'], 'aud' => 'admin']);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ])->call('OPTIONS', '/api/v1/admin/entries', [], [
            config('jwt.cookies.access') => $accessToken,
        ]);

        // Preflight должен обрабатываться CORS middleware
        $this->assertNotEquals(419, $response->status(), 'OPTIONS preflight should not trigger CSRF check');
    }

    public function test_admin_endpoint_with_valid_csrf_succeeds(): void
    {
        // Проверяем что админский endpoint работает с валидным CSRF
        $user = \App\Models\User::factory()->create();
        
        // Используем test helper который автоматически добавляет JWT + CSRF
        $response = $this->postJsonAsAdmin('/api/v1/admin/options', [
            'key' => 'test_key',
            'value' => 'test_value',
        ], $user);

        // Не должен возвращать 419 (может вернуть другую ошибку валидации, но не CSRF)
        $this->assertNotEquals(419, $response->status(), 'Request with valid CSRF should not return 419');
    }

    public function test_csrf_token_issued_via_current_user_endpoint(): void
    {
        // CSRF токен выдается через GET /admin/auth/current
        $user = \App\Models\User::factory()->create();
        
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $accessToken = $jwtService->issueAccessToken($user->id, ['scp' => ['admin'], 'aud' => 'admin']);
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->call('GET', '/api/v1/admin/auth/current', [], [
            config('jwt.cookies.access') => $accessToken,
        ]);

        $response->assertOk();
        
        // Проверка наличия CSRF cookie в Set-Cookie заголовке
        $this->assertTrue($response->headers->has('Set-Cookie'), 'CSRF cookie should be set');
        $cookieName = $this->getCsrfCookieName();
        $setCookieHeader = $response->headers->get('Set-Cookie');
        $this->assertStringContainsString($cookieName . '=', $setCookieHeader, 'CSRF cookie should be present');
        $this->assertStringNotContainsString('HttpOnly', $setCookieHeader, 'CSRF cookie must NOT be HttpOnly');
    }

    private function typeUri(ErrorCode $code): string
    {
        return config('errors.types.' . $code->value . '.uri');
    }

    private function title(ErrorCode $code): string
    {
        return config('errors.types.' . $code->value . '.title');
    }
}

