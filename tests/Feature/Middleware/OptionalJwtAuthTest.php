<?php

declare(strict_types=1);

use App\Domain\Auth\JwtService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\getJson;

/**
 * Feature-тесты для OptionalJwtAuth middleware.
 *
 * Тестирует опциональную JWT аутентификацию:
 * - Устанавливает пользователя при валидном токене
 * - Пропускает запрос без ошибки при отсутствии токена
 * - Пропускает запрос без ошибки при невалидном токене
 */

// Создаём тестовый роут для проверки middleware
beforeEach(function () {
    Route::middleware(['api', 'jwt.auth.optional'])->prefix('api/test')->group(function () {
        Route::get('/optional-auth', function () {
            return response()->json([
                'authenticated' => Auth::guard('api')->check(),
                'user_id' => Auth::guard('api')->id(),
            ]);
        });
        
        Route::get('/guard', function () {
            return response()->json([
                'api_guard' => Auth::getDefaultDriver(),
            ]);
        });
    });
});

test('sets user when valid access token is provided', function () {
    $user = User::factory()->create();
    $jwt = app(JwtService::class);
    $token = $jwt->issueAccessToken($user->id);

    $cookieName = config('jwt.cookies.access');
    // В Laravel тестах для API запросов cookie нужно передавать через заголовок
    $response = $this->withHeader('Cookie', $cookieName . '=' . $token)
        ->getJson('/api/test/optional-auth');

    $response->assertOk()
        ->assertJson([
            'authenticated' => true,
            'user_id' => $user->id,
        ]);
});

test('passes request without error when no token is provided', function () {
    $response = getJson('/api/test/optional-auth');

    $response->assertOk()
        ->assertJson([
            'authenticated' => false,
            'user_id' => null,
        ]);
});

test('passes request without error when token is invalid', function () {
    $response = $this->withCookie(config('jwt.cookies.access'), 'invalid.token.here')
        ->getJson('/api/test/optional-auth');

    $response->assertOk()
        ->assertJson([
            'authenticated' => false,
            'user_id' => null,
        ]);
});

test('passes request without error when token is expired', function () {
    $user = User::factory()->create();
    $jwt = app(JwtService::class);
    
    // Создаём токен с истёкшим сроком действия
    $expiredToken = $jwt->encode(
        $user->id,
        'access',
        -3600, // отрицательный TTL = токен уже истёк
        []
    );

    $response = $this->withCookie(config('jwt.cookies.access'), $expiredToken)
        ->getJson('/api/test/optional-auth');

    $response->assertOk()
        ->assertJson([
            'authenticated' => false,
            'user_id' => null,
        ]);
});

test('passes request without error when user does not exist', function () {
    $jwt = app(JwtService::class);
    // Создаём токен для несуществующего пользователя
    $nonExistentUserId = 99999;
    $token = $jwt->issueAccessToken($nonExistentUserId);

    $response = $this->withCookie(config('jwt.cookies.access'), $token)
        ->getJson('/api/test/optional-auth');

    $response->assertOk()
        ->assertJson([
            'authenticated' => false,
            'user_id' => null,
        ]);
});

test('passes request without error when token has invalid subject claim', function () {
    $jwt = app(JwtService::class);
    // Создаём токен с невалидным subject (не число)
    $token = $jwt->encode(
        'invalid-subject',
        'access',
        3600,
        []
    );

    $response = $this->withCookie(config('jwt.cookies.access'), $token)
        ->getJson('/api/test/optional-auth');

    $response->assertOk()
        ->assertJson([
            'authenticated' => false,
            'user_id' => null,
        ]);
});

test('uses api guard when user is authenticated', function () {
    $user = User::factory()->create();
    $jwt = app(JwtService::class);
    $token = $jwt->issueAccessToken($user->id);

    $cookieName = config('jwt.cookies.access');
    // В Laravel тестах для API запросов cookie нужно передавать через заголовок
    $response = $this->withHeader('Cookie', $cookieName . '=' . $token)
        ->getJson('/api/test/guard');

    $response->assertOk()
        ->assertJson([
            'api_guard' => 'api',
        ]);
});

