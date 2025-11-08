<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['admin.auth'])->get('/test/admin', function () {
            return response()->json([
                'message' => 'OK',
                'user_id' => Auth::id(),
                'guard' => Auth::getDefaultDriver(),
            ]);
        });
    }

    public function test_admin_auth_without_token_returns_401(): void
    {
        $response = $this->getJson('/test/admin');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_admin_auth_with_invalid_token_returns_401(): void
    {
        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), 'invalid-token');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_admin_auth_with_regular_token_returns_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        // Issue regular token directly (aud=api, no admin scope)
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $regularToken = $jwtService->issueAccessToken($user->id);

        // Try to access admin route with regular token (missing aud=admin and scp=['admin'])
        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $regularToken);

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin privileges are required.',
        ]);
    }

    public function test_admin_auth_with_admin_token_but_non_admin_user_succeeds(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123'), 'is_admin' => false]);

        // Issue admin token manually (aud=admin, scp=['admin'])
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $adminToken = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
        ]);

        // AdminAuth middleware only checks JWT validity, not user permissions
        // Specific authorization (is_admin or abilities) is checked by separate middleware/controllers
        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $adminToken);

        $response->assertOk();
        $response->assertJson([
            'message' => 'OK',
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_auth_with_valid_admin_token_succeeds(): void
    {
        $adminUser = User::factory()->create(['password' => bcrypt('password123'), 'is_admin' => true]);

        // Issue admin token manually (aud=admin, scp=['admin'])
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $adminToken = $jwtService->issueAccessToken($adminUser->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
        ]);

        // Access admin route with valid admin token
        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $adminToken);

        $response->assertOk();
        $response->assertJson([
            'message' => 'OK',
            'user_id' => $adminUser->id,
            'guard' => 'admin',
        ]);
    }

    public function test_admin_auth_with_missing_user_returns_401(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $adminToken = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
        ]);

        $user->delete();

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $adminToken);

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_admin_auth_with_audience_mismatch_returns_403(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'api',
            'scp' => ['admin'],
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(403);
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin privileges are required.',
        ]);
    }

    public function test_admin_auth_with_missing_admin_scope_returns_403(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['cms'],
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(403);
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin privileges are required.',
        ]);
    }

    public function test_admin_auth_with_missing_subject_returns_401(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
            'sub' => null,
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_admin_auth_with_non_integer_subject_returns_401(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
            'sub' => 'abc',
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_problem_json_header_is_stable(): void
    {
        $response = $this->getJson('/test/admin');

        $response->assertStatus(401);
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    public function test_admin_scope_scalar_string_is_accepted(): void
    {
        $adminUser = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $adminToken = $jwtService->issueAccessToken($adminUser->id, [
            'aud' => 'admin',
            'scp' => 'admin', // scalar string instead of array
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $adminToken);

        $response->assertOk();
        $response->assertJson([
            'message' => 'OK',
            'user_id' => $adminUser->id,
        ]);
    }

    public function test_scope_is_case_sensitive(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['Admin'], // wrong case
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(403);
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
        ]);
    }

    public function test_subject_zero_returns_401(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
            'sub' => 0,
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
    }

    public function test_subject_negative_returns_401(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
            'sub' => -1,
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
    }

    public function test_subject_with_spaces_is_trimmed(): void
    {
        $adminUser = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($adminUser->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
            'sub' => ' ' . $adminUser->id . ' ',
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertOk();
        $response->assertJson([
            'message' => 'OK',
            'user_id' => $adminUser->id,
        ]);
    }

    public function test_www_authenticate_only_on_401(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(403);
        $this->assertNull($response->headers->get('WWW-Authenticate'));
    }

    public function test_adds_no_store_on_401(): void
    {
        $response = $this->getJson('/test/admin');

        $response->assertStatus(401);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_logs_reason_for_unauthorized(): void
    {
        \Log::spy();

        $this->getJson('/test/admin');

        \Log::shouldHaveReceived('log')
            ->once()
            ->with('warning', \Mockery::pattern('/\[AdminAuth\] 401: missing_token/'), \Mockery::type('array'));
    }

    public function test_logs_reason_for_forbidden(): void
    {
        \Log::spy();

        $user = User::factory()->create(['is_admin' => false]);
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id);

        $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        \Log::shouldHaveReceived('log')
            ->once()
            ->with('notice', \Mockery::pattern('/\[AdminAuth\] 403: invalid_audience/'), \Mockery::type('array'));
    }
}

