<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set JWT leeway to account for clock drift between server and client
        // This prevents flaky tests when tokens are near expiration boundaries
        JWT::$leeway = 5; // 5 seconds tolerance
    }

    /**
     * Make a GET JSON request with an unencrypted cookie.
     * Useful for JWT cookies that should not be encrypted.
     *
     * @param string $uri
     * @param string $cookieName
     * @param string $cookieValue
     * @param array $headers
     * @return \Illuminate\Testing\TestResponse
     */
    public function getJsonWithUnencryptedCookie(string $uri, string $cookieName, string $cookieValue, array $headers = []): \Illuminate\Testing\TestResponse
    {
        $server = $this->transformHeadersToServerVars(array_merge($headers, [
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ]));

        return $this->call('GET', $uri, [], [$cookieName => $cookieValue], [], $server);
    }

    /**
     * Make a POST JSON request with an unencrypted cookie.
     * Useful for JWT cookies that should not be encrypted.
     *
     * @param string $uri
     * @param string $cookieName
     * @param string $cookieValue
     * @param array $data
     * @param array $headers
     * @return \Illuminate\Testing\TestResponse
     */
    public function postJsonWithUnencryptedCookie(string $uri, string $cookieName, string $cookieValue, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        $server = $this->transformHeadersToServerVars(array_merge($headers, [
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ]));

        return $this->call('POST', $uri, $data, [$cookieName => $cookieValue], [], $server, json_encode($data));
    }

    /**
     * Get a cookie from the response without decryption.
     * 
     * This method is useful for JWT and CSRF cookies that are not encrypted.
     * Using the standard getCookie() method causes DecryptException.
     *
     * @param \Illuminate\Testing\TestResponse $response
     * @param string $cookieName
     * @return \Symfony\Component\HttpFoundation\Cookie|null
     */
    protected function getUnencryptedCookie(\Illuminate\Testing\TestResponse $response, string $cookieName): ?\Symfony\Component\HttpFoundation\Cookie
    {
        $cookies = $response->headers->getCookies();
        
        return collect($cookies)->first(function ($cookie) use ($cookieName) {
            return $cookie->getName() === $cookieName;
        });
    }

    /**
     * Make a POST JSON request with multiple unencrypted cookies.
     * Useful for requests that need both JWT and CSRF cookies.
     *
     * @param string $uri
     * @param array $data
     * @param array $cookies  Array of cookie name => value pairs
     * @param array $headers
     * @return \Illuminate\Testing\TestResponse
     */
    protected function postJsonWithCookies(string $uri, array $data = [], array $cookies = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        $server = $this->transformHeadersToServerVars(array_merge($headers, [
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ]));

        return $this->call('POST', $uri, $data, $cookies, [], $server, json_encode($data));
    }

    /**
     * Make a POST JSON request as an admin with proper JWT and CSRF tokens.
     *
     * @param string $uri
     * @param array $data
     * @param \App\Models\User $user
     * @return \Illuminate\Testing\TestResponse
     */
    protected function postJsonAsAdmin(string $uri, array $data, \App\Models\User $user): \Illuminate\Testing\TestResponse
    {
        [$cookies, $headers] = $this->getAdminAuthContext($user);
        return $this->postJsonWithCookies($uri, $data, $cookies, $headers);
    }

    /**
     * Make a GET JSON request as an admin with proper JWT and CSRF tokens.
     *
     * @param string $uri
     * @param \App\Models\User $user
     * @return \Illuminate\Testing\TestResponse
     */
    protected function getJsonAsAdmin(string $uri, \App\Models\User $user): \Illuminate\Testing\TestResponse
    {
        [$cookies, $headers] = $this->getAdminAuthContext($user);
        
        $server = $this->transformHeadersToServerVars(array_merge($headers, [
            'Accept' => 'application/json',
        ]));

        return $this->call('GET', $uri, [], $cookies, [], $server);
    }

    /**
     * Make a DELETE JSON request as an admin with proper JWT and CSRF tokens.
     *
     * @param string $uri
     * @param array $data
     * @param \App\Models\User $user
     * @return \Illuminate\Testing\TestResponse
     */
    protected function deleteJsonAsAdmin(string $uri, array $data, \App\Models\User $user): \Illuminate\Testing\TestResponse
    {
        [$cookies, $headers] = $this->getAdminAuthContext($user);
        
        $server = $this->transformHeadersToServerVars(array_merge($headers, [
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ]));

        return $this->call('DELETE', $uri, $data, $cookies, [], $server, json_encode($data));
    }

    /**
     * Get admin authentication context (cookies and headers).
     *
     * @param \App\Models\User $user
     * @return array [cookies, headers]
     */
    private function getAdminAuthContext(\App\Models\User $user): array
    {
        // Generate JWT tokens for the admin user
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $accessToken = $jwtService->issueAccessToken($user->id, ['scp' => ['admin'], 'aud' => 'admin']);
        
        // Generate CSRF token
        $csrfToken = \Illuminate\Support\Str::random(40);
        $csrfCookieName = config('security.csrf.cookie_name', 'cms_csrf');
        
        $cookies = [
            config('jwt.cookies.access') => $accessToken,
            $csrfCookieName => $csrfToken,
        ];
        
        $headers = [
            'X-CSRF-Token' => $csrfToken,
        ];
        
        return [$cookies, $headers];
    }

    /**
     * Get CSRF context (without authentication).
     *
     * @return array [cookies, headers]
     */
    private function getCsrfContext(): array
    {
        $csrfToken = \Illuminate\Support\Str::random(40);
        $csrfCookieName = config('security.csrf.cookie_name', 'cms_csrf');
        
        $cookies = [
            $csrfCookieName => $csrfToken,
        ];
        
        $headers = [
            'X-CSRF-Token' => $csrfToken,
        ];
        
        return [$cookies, $headers];
    }

    /**
     * Make a POST JSON request with CSRF token but no authentication.
     *
     * @param string $uri
     * @param array $data
     * @return \Illuminate\Testing\TestResponse
     */
    protected function postJsonWithCsrf(string $uri, array $data): \Illuminate\Testing\TestResponse
    {
        [$cookies, $headers] = $this->getCsrfContext();
        return $this->postJsonWithCookies($uri, $data, $cookies, $headers);
    }

    /**
     * Make a DELETE JSON request with CSRF token but no authentication.
     *
     * @param string $uri
     * @param array $data
     * @return \Illuminate\Testing\TestResponse
     */
    protected function deleteJsonWithCsrf(string $uri, array $data): \Illuminate\Testing\TestResponse
    {
        [$cookies, $headers] = $this->getCsrfContext();
        
        $server = $this->transformHeadersToServerVars(array_merge($headers, [
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ]));

        return $this->call('DELETE', $uri, $data, $cookies, [], $server, json_encode($data));
    }
}
