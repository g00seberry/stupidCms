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
}
