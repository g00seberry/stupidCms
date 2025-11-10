<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\JwtService;
use App\Http\Middleware\JwtAuth;
use App\Models\User;
use App\Support\Http\ProblemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class JwtAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['jwt.auth'])->get('/test/jwt', function () {
            return response()->json([
                'message' => 'OK',
                'user_id' => Auth::id(),
                'guard' => Auth::getDefaultDriver(),
            ]);
        });
    }

    public function test_jwt_auth_without_token_returns_401(): void
    {
        $response = $this->getJson('/test/jwt');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertJson([
            'code' => 'JWT_ACCESS_TOKEN_MISSING',
            'detail' => ProblemType::UNAUTHORIZED->defaultDetail(),
        ]);
    }

    public function test_jwt_auth_with_invalid_token_returns_401(): void
    {
        $response = $this->getJsonWithUnencryptedCookie('/test/jwt', config('jwt.cookies.access'), 'invalid-token');

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer');
        $response->assertJson([
            'code' => 'JWT_ACCESS_TOKEN_INVALID',
            'detail' => ProblemType::UNAUTHORIZED->defaultDetail(),
        ]);
    }

    public function test_jwt_auth_with_valid_token_succeeds(): void
    {
        $user = User::factory()->create();

        $jwtService = app(JwtService::class);
        $token = $jwtService->issueAccessToken($user->id);

        $response = $this->getJsonWithUnencryptedCookie('/test/jwt', config('jwt.cookies.access'), $token);

        $response->assertOk();
        $response->assertJson([
            'message' => 'OK',
            'user_id' => $user->id,
            'guard' => 'api',
        ]);
    }

    public function test_jwt_auth_with_missing_user_returns_401(): void
    {
        $user = User::factory()->create();

        $jwtService = app(JwtService::class);
        $token = $jwtService->issueAccessToken($user->id);

        $user->delete();

        $response = $this->getJsonWithUnencryptedCookie('/test/jwt', config('jwt.cookies.access'), $token);

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer');
        $response->assertJson([
            'code' => 'JWT_USER_NOT_FOUND',
            'detail' => ProblemType::UNAUTHORIZED->defaultDetail(),
        ]);
    }

    public function test_jwt_auth_with_invalid_subject_returns_401(): void
    {
        $user = User::factory()->create();

        $jwtService = app(JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'sub' => null,
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/jwt', config('jwt.cookies.access'), $token);

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer');
        $response->assertJson([
            'code' => 'JWT_SUBJECT_INVALID',
            'detail' => ProblemType::UNAUTHORIZED->defaultDetail(),
        ]);
    }

    public function test_without_middleware_allows_access_even_without_token(): void
    {
        Route::withoutMiddleware([JwtAuth::class])->get('/test/jwt-unprotected', fn () => response()->json(['message' => 'OK']));

        $response = $this->getJson('/test/jwt-unprotected');

        $response->assertOk();
        $response->assertJson(['message' => 'OK']);
    }
}
