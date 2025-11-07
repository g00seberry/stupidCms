<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthCsrfTest extends TestCase
{
    use RefreshDatabase;

    private function getCsrfCookieName(): string
    {
        return config('security.csrf.cookie_name', 'cms_csrf');
    }

    public function test_csrf_endpoint_returns_token_and_cookie(): void
    {
        $response = $this->getJson('/api/v1/auth/csrf');

        $response->assertOk();
        $response->assertJsonStructure(['csrf']);
        
        // Проверка наличия cookie в Set-Cookie заголовке
        $this->assertTrue($response->headers->has('Set-Cookie'), 'CSRF cookie should be set');
        $cookieName = $this->getCsrfCookieName();
        $setCookieHeader = $response->headers->get('Set-Cookie');
        $this->assertStringContainsString($cookieName . '=', $setCookieHeader, 'CSRF cookie should be present');
        $this->assertStringNotContainsString('HttpOnly', $setCookieHeader, 'CSRF cookie must NOT be HttpOnly');
        
        // Проверка, что токен в ответе присутствует
        $token = $response->json('csrf');
        $this->assertNotEmpty($token, 'CSRF token should be present in response');
        $this->assertEquals(40, strlen($token), 'CSRF token should be 40 characters long');
    }

    public function test_csrf_cookie_attributes_are_correct(): void
    {
        $response = $this->getJson('/api/v1/auth/csrf');
        $response->assertOk();

        $cookieName = $this->getCsrfCookieName();
        $setCookieHeader = $response->headers->get('Set-Cookie');
        
        // Проверка отсутствия HttpOnly
        $this->assertStringNotContainsString('HttpOnly', $setCookieHeader, 'CSRF cookie must NOT be HttpOnly');
        
        // Проверка Secure (зависит от конфига) - case insensitive
        $csrfConfig = config('security.csrf');
        $setCookieHeaderLower = strtolower($setCookieHeader);
        if ($csrfConfig['secure']) {
            $this->assertStringContainsString('secure', $setCookieHeaderLower, 'CSRF cookie should be Secure when configured');
        }
        
        // Проверка SameSite - case insensitive
        $expectedSameSite = strtolower($csrfConfig['samesite']);
        if ($expectedSameSite === 'none') {
            $this->assertStringContainsString('samesite=none', $setCookieHeaderLower, 'CSRF cookie should have SameSite=None');
            $this->assertStringContainsString('secure', $setCookieHeaderLower, 'CSRF cookie with SameSite=None must be Secure');
        } elseif ($expectedSameSite === 'lax') {
            $this->assertStringContainsString('samesite=lax', $setCookieHeaderLower, 'CSRF cookie should have SameSite=Lax');
        } else {
            $this->assertStringContainsString('samesite=strict', $setCookieHeaderLower, 'CSRF cookie should have SameSite=Strict');
        }
        
        // Проверка Path - case insensitive
        $expectedPath = $csrfConfig['path'];
        $this->assertStringContainsString('path=' . $expectedPath, $setCookieHeaderLower, 'CSRF cookie should have correct Path');
        
        // Проверка Domain (если указан) - case insensitive
        if ($csrfConfig['domain']) {
            $this->assertStringContainsString('domain=' . strtolower($csrfConfig['domain']), $setCookieHeaderLower, 'CSRF cookie should have correct Domain');
        }
    }

    public function test_post_without_csrf_token_returns_419_with_new_cookie(): void
    {
        // Попытка POST запроса без CSRF токена
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(419);
        $response->assertHeader('Content-Type', 'application/problem+json');
        
        // Проверка RFC 7807 формата
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'CSRF Token Mismatch',
            'status' => 419,
            'detail' => 'CSRF token mismatch.',
        ]);
        
        // Проверка, что при 419 выдается новый CSRF cookie
        $cookieName = $this->getCsrfCookieName();
        $this->assertTrue($response->headers->has('Set-Cookie'), 'New CSRF cookie should be issued on 419');
        $setCookieHeader = $response->headers->get('Set-Cookie');
        $this->assertStringContainsString($cookieName . '=', $setCookieHeader, 'New CSRF cookie should be present');
    }

    public function test_post_with_valid_csrf_token_succeeds(): void
    {
        // Получаем CSRF токен
        $csrfResponse = $this->getJson('/api/v1/auth/csrf');
        $csrfResponse->assertOk();
        
        $token = $csrfResponse->json('csrf');
        $this->assertNotEmpty($token);
        $cookieName = $this->getCsrfCookieName();
        
        // Используем call() напрямую для установки незашифрованного cookie
        // Аналогично тому, как это делается для JWT cookies
        $server = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-CSRF-Token' => $token,
        ]);
        
        $response = $this->call('POST', '/api/v1/auth/logout', [], [$cookieName => $token], [], $server);

        // Logout возвращает 204 No Content
        $this->assertEquals(204, $response->status());
    }

    public function test_post_with_x_xsrf_token_header_succeeds(): void
    {
        // Получаем CSRF токен
        $csrfResponse = $this->getJson('/api/v1/auth/csrf');
        $csrfResponse->assertOk();
        
        $token = $csrfResponse->json('csrf');
        $this->assertNotEmpty($token);
        $cookieName = $this->getCsrfCookieName();
        
        // Используем X-XSRF-TOKEN заголовок вместо X-CSRF-Token
        $server = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-XSRF-TOKEN' => $token,
        ]);
        
        $response = $this->call('POST', '/api/v1/auth/logout', [], [$cookieName => $token], [], $server);

        // Logout возвращает 204 No Content
        $this->assertEquals(204, $response->status());
    }

    public function test_post_with_mismatched_csrf_token_returns_419(): void
    {
        // Получаем CSRF токен
        $csrfResponse = $this->getJson('/api/v1/auth/csrf');
        $csrfResponse->assertOk();
        
        $token = $csrfResponse->json('csrf');
        $this->assertNotEmpty($token);
        $cookieName = $this->getCsrfCookieName();
        
        // Отправляем POST запрос с неверным CSRF токеном в заголовке
        // Cookie содержит правильный токен, но заголовок содержит неправильный
        $response = $this->withCookie($cookieName, $token)
            ->withHeader('X-CSRF-Token', 'wrong-token')
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(419);
        $response->assertHeader('Content-Type', 'application/problem+json');
        
        // Проверка RFC 7807 формата
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'CSRF Token Mismatch',
            'status' => 419,
            'detail' => 'CSRF token mismatch.',
        ]);
        
        // Проверка, что при 419 выдается новый CSRF cookie
        $this->assertTrue($response->headers->has('Set-Cookie'), 'New CSRF cookie should be issued on 419');
    }

    public function test_post_without_csrf_cookie_returns_419(): void
    {
        // Отправляем POST запрос с заголовком, но без cookie
        $response = $this->withHeader('X-CSRF-Token', 'some-token')
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(419);
        $response->assertHeader('Content-Type', 'application/problem+json');
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

    public function test_get_request_bypasses_csrf_check(): void
    {
        // GET запросы не требуют CSRF токена
        $response = $this->getJson('/api/v1/auth/csrf');

        $response->assertOk();
    }

    public function test_head_request_bypasses_csrf_check(): void
    {
        // HEAD запросы не требуют CSRF токена
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->call('HEAD', '/api/v1/auth/csrf');

        $this->assertEquals(200, $response->status());
    }

    public function test_options_preflight_request_bypasses_csrf_check(): void
    {
        // OPTIONS preflight запросы не требуют CSRF токена
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ])->call('OPTIONS', '/api/v1/auth/logout');

        // Preflight должен обрабатываться CORS middleware
        $this->assertNotEquals(419, $response->status(), 'OPTIONS preflight should not trigger CSRF check');
    }

    public function test_cross_origin_request_with_credentials_and_valid_csrf_succeeds(): void
    {
        // Получаем CSRF токен
        $csrfResponse = $this->getJson('/api/v1/auth/csrf');
        $csrfResponse->assertOk();
        
        $token = $csrfResponse->json('csrf');
        $this->assertNotEmpty($token);
        $cookieName = $this->getCsrfCookieName();
        
        // Симулируем cross-origin запрос с credentials
        $server = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-CSRF-Token' => $token,
            'Origin' => 'https://app.example.com',
        ]);
        
        $response = $this->call('POST', '/api/v1/auth/logout', [], [$cookieName => $token], [], $server);

        // Запрос должен пройти при валидном CSRF токене
        $this->assertEquals(204, $response->status());
    }

    public function test_csrf_cookie_uses_config_values(): void
    {
        // Проверяем, что cookie использует значения из config
        $response = $this->getJson('/api/v1/auth/csrf');
        $response->assertOk();

        $csrfConfig = config('security.csrf');
        $setCookieHeader = $response->headers->get('Set-Cookie');
        
        // Проверка имени cookie из конфига
        $this->assertStringContainsString($csrfConfig['cookie_name'] . '=', $setCookieHeader);
        
        // Проверка Path из конфига - case insensitive
        $setCookieHeaderLower = strtolower($setCookieHeader);
        $this->assertStringContainsString('path=' . $csrfConfig['path'], $setCookieHeaderLower);
        
        // Проверка Domain из конфига (если указан) - case insensitive
        if ($csrfConfig['domain']) {
            $this->assertStringContainsString('domain=' . strtolower($csrfConfig['domain']), $setCookieHeaderLower);
        }
    }
}
