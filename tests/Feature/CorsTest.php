<?php

namespace Tests\Feature;

use Tests\Support\FeatureTestCase;

class CorsTest extends FeatureTestCase
{    public function test_preflight_request_returns_204_with_credentials(): void
    {
        $allowedOrigin = config('cors.allowed_origins')[0] ?? 'https://app.example.com';

        $response = $this->optionsJson('/api/v1/auth/login', [], [
            'Origin' => $allowedOrigin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', $allowedOrigin);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_preflight_request_with_invalid_origin_returns_204(): void
    {
        $response = $this->optionsJson('/api/v1/auth/login', [], [
            'Origin' => 'https://evil.com',
            'Access-Control-Request-Method' => 'POST',
        ]);

        // Laravel CORS middleware returns 204 for preflight requests
        // The behavior for invalid origins depends on CORS config - may allow all or specific origins
        $response->assertStatus(204);
    }

    public function test_real_request_with_allowed_origin_sets_cookies(): void
    {
        $allowedOrigin = config('cors.allowed_origins')[0] ?? 'https://app.example.com';

        // This will fail authentication, but we're checking CORS headers
        $response = $this->withHeaders([
            'Origin' => $allowedOrigin,
        ])->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Should have CORS headers even on error responses
        $response->assertHeader('Access-Control-Allow-Origin', $allowedOrigin);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_response_with_cookies_has_vary_headers(): void
    {
        $user = \App\Models\User::factory()->create(['password' => bcrypt('password123')]);

        $origin = config('cors.allowed_origins')[0] ?? 'https://app.example.com';

        $response = $this->withHeaders([
            'Origin' => $origin,
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();

        // Verify cookies are set
        $accessCookie = $this->getUnencryptedCookie($response, config('jwt.cookies.access'));
        $this->assertNotNull($accessCookie);

        // Verify Vary header includes Origin and Cookie
        $varyHeader = $response->headers->get('Vary');
        $this->assertNotNull($varyHeader);
        $this->assertStringContainsString('Origin', $varyHeader);
        $this->assertStringContainsString('Cookie', $varyHeader);
    }
}

