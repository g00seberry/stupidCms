# Задача 41. Админ middleware `admin.auth`

## Резюме
Сделать middleware, который защищает все эндпоинты `/api/v1/admin/*`:
- Извлекает **access JWT** из cookie `cms_at`.
- Проверяет подпись/сроки/iss/aud и тип `typ=access` (см. Задачу 36).
- Находит пользователя и помещает его в `Auth`.
- Проверяет флаг администратора (`users.is_admin = 1`).
- При несоответствии — корректные статусы **401/403** (в формате RFC7807, см. Задачу 43).

**Критерии приёмки:** `/api/v1/admin/*` недоступен без логина и для не‑админа (401/403).

Связанные: 27 (политики), 36–40 (JWT/куки/CSRF), 43 (формат ошибок RFC7807).

---

## Реализация middleware
`app/Http/Middleware/AdminAuth.php`
```php
namespace App\Http\Middleware;

use App\Domain\Auth\JwtService;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AdminAuth
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next)
    {
        $cookie = config('jwt.cookies.access');
        $raw = (string) $request->cookie($cookie, '');
        if ($raw === '') {
            return $this->unauthorized();
        }

        try {
            $verified = $this->jwt->verify($raw, 'access');
            $claims = $verified['claims'];
        } catch (\Throwable $e) {
            return $this->unauthorized();
        }

        $user = User::query()->find($claims['sub'] ?? null);
        if (! $user) {
            return $this->unauthorized();
        }

        if (! $user->is_admin) {
            return $this->forbidden();
        }

        // Заселить пользователя в контекст
        Auth::setUser($user);

        return $next($request);
    }

    private function unauthorized()
    {
        return response()->json([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.'
        ], 401)->withHeaders(['Content-Type' => 'application/problem+json']);
    }

    private function forbidden()
    {
        return response()->json([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin privileges are required.'
        ], 403)->withHeaders(['Content-Type' => 'application/problem+json']);
    }
}
```

### Регистрация
`app/Http/Kernel.php`:
```php
protected $routeMiddleware = [
    // ...
    'admin.auth' => \App\Http\Middleware\AdminAuth::class,
];
```

`routes/api.php`:
```php
Route::prefix('/v1/admin')->middleware(['api','admin.auth'])->group(function(){
    Route::get('/ping', fn() => response()->json(['ok' => true]));
    // ... все админ‑эндпоинты
});
```

---

## Поведение и статусы
- Нет cookie `cms_at` / подпись невалидна / истёк → **401 Unauthorized**.
- Пользователь найден, но не админ → **403 Forbidden**.
- При успехе — `Auth::user()` доступен в контроллерах/политиках.

---

## Тесты (Feature)
```php
public function test_admin_group_closed_without_login()
{
    $this->getJson('/api/v1/admin/ping')->assertStatus(401);
}

public function test_non_admin_forbidden()
{
    $user = User::factory()->create(['is_admin' => false]);
    $jwt = app(JwtService::class)->issueAccessToken($user->id);
    $this->withCookie(config('jwt.cookies.access'), $jwt)
         ->getJson('/api/v1/admin/ping')
         ->assertStatus(403);
}

public function test_admin_ok()
{
    $user = User::factory()->create(['is_admin' => true]);
    $jwt = app(JwtService::class)->issueAccessToken($user->id);
    $this->withCookie(config('jwt.cookies.access'), $jwt)
         ->getJson('/api/v1/admin/ping')
         ->assertOk();
}
```

---

## Приёмка (Definition of Done)
- [ ] Middleware `admin.auth` подключён и защищает `/api/v1/admin/*`.
- [ ] 401 для незалогиненных/битых токенов; 403 для не‑админов.
- [ ] Пользователь пробрасывается в `Auth`.
- [ ] Тес