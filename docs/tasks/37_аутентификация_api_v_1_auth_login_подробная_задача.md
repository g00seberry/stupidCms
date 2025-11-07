# Задача 37. Аутентификация: `POST /api/v1/auth/login`

## Резюме
Реализовать endpoint входа по email/паролю. При успешной аутентификации выдать **два HttpOnly Secure cookie**: `cms_at` (access JWT) и `cms_rt` (refresh JWT). При ошибке — `401 Unauthorized` без уточнений.

**Критерии приёмки:**
- Успешный вход → HTTP 200 + установлены cookies `cms_at`, `cms_rt` с флагами `HttpOnly+Secure+SameSite=Strict`.
- Неверные учётные данные → HTTP 401 без cookies.

Связанные: 36 (модель JWT), 38 (refresh), 39 (logout/rotate), 27 (политики), 80 (аудит входов).

---

## Маршрут
`routes/api.php`:
```php
use App\Http\Controllers\Auth\LoginController;

Route::post('/v1/auth/login', [LoginController::class, 'login'])
    ->middleware(['throttle:login']);
```

В `App\Providers\RouteServiceProvider` убедиться, что префикс `/api` настроен, а группа использует middleware `api`.

### Rate limiting
В `App\Providers\RouteServiceProvider`:
```php
RateLimiter::for('login', function (Request $request) {
    $key = 'login:'.Str::lower($request->input('email')).'|'.$request->ip();
    return Limit::perMinute(5)->by($key);
});
```

---

## Валидация (FormRequest)
`app/Http/Requests/Auth/LoginRequest.php`
```php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required','email:strict','lowercase','max:254'],
            'password' => ['required','string','min:8','max:200'],
        ];
    }
}
```

---

## Контроллер
`app/Http/Controllers/Auth/LoginController.php`
```php
namespace App\Http\Controllers\Auth;

use App\Domain\Auth\JwtService;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\JwtCookies;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

final class LoginController
{
    public function __construct(private JwtService $jwt) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower($request->input('email'));
        $password = (string) $request->input('password');

        $user = User::query()->where('email', $email)->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            // Ответ без уточнения, что именно неверно
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Выпуск токенов
        $access = $this->jwt->issueAccessToken($user->getKey(), ['scp' => ['api']]);
        $refresh = $this->jwt->issueRefreshToken($user->getKey());

        // Ответ + cookies
        return response()->json([
            'user' => [
                'id' => (int) $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
        ])->withCookie(JwtCookies::access($access))
          ->withCookie(JwtCookies::refresh($refresh));
    }
}
```

> Пароли в `users.password` хэшируются стандартным хэшем Laravel (bcrypt/argon2id). Любой ответ 401 не раскрывает существование email.

---

## Cookies
- `cms_at` — короткоживущий access JWT.
- `cms_rt` — долгоживущий refresh JWT.
- Флаги: `HttpOnly`, `Secure`, `SameSite=Strict`, `Path=/`, `Domain` из `SESSION_DOMAIN`.
- Клиент должен слать cookies автоматически (fetch `credentials: 'include'`).

---

## Ответы (примеры)
### 200 OK
```json
{
  "user": { "id": 1, "email": "admin@example.com", "name": "Admin" }
}
```
С заголовками `Set-Cookie: cms_at=...; HttpOnly; Secure; SameSite=Strict` и `cms_rt=...; HttpOnly; Secure; SameSite=Strict`.

### 401 Unauthorized
```json
{ "message": "Unauthorized" }
```

---

## Тесты (Feature)
```php
public function test_login_success_sets_cookies()
{
    $user = User::factory()->create(['password' => bcrypt('secretPass123')]);

    $res = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'secretPass123',
    ]);

    $res->assertOk();
    $res->assertCookie(config('jwt.cookies.access'));
    $res->assertCookie(config('jwt.cookies.refresh'));
}

public function test_login_failure_401()
{
    $res = $this->postJson('/api/v1/auth/login', [
        'email' => 'no@user.tld',
        'password' => 'wrong',
    ]);
    $res->assertStatus(401);
    $res->assertCookieMissing(config('jwt.cookies.access'));
}
```

---

## Безопасность и наблюдаемость
- Rate‑limit (5/мин на связку email+IP).
- Аудит: логировать `user_id`, IP, user‑agent, результат (`success/failed`).
- Не возвращать детальные причины (аутентификация слепая).
- В будущем: 2FA/OTP, отключение входа для заблокированных пользователей.

---

## Приёмка (Definition of Done)
- [ ] Маршрут и контроллер реализованы.
- [ ] Cookies `cms_at/cms_rt` с корректными флагами устанавливаются при успехе.
- [ ] Неверные креды → 401 без cookies.
- [ ] Тесты зелёные.

