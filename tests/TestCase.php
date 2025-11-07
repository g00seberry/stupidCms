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
}
