# Задача 40. CSRF-cookie + заголовок

## Резюме
Добавить CSRF‑защиту для API: эндпоинт выдаёт `cms_csrf` (НЕ HttpOnly), а middleware сверяет заголовок `X-CSRF-Token` со значением cookie на всех **state‑changing** запросах.

**Критерии приёмки:** POST без токена → 419/403; с корректным токеном → 200.

Связанные: 37–39 (login/refresh/logout).

---

## Эндпоинт
```php
Route::get('/v1/auth/csrf', [CsrfController::class, 'issue']);
```

### Контроллер
```php
final class CsrfController
{
    public function issue(): JsonResponse
    {
        $token = Str::random(40);
        $c = config('jwt.cookies');

        $cookie = Cookie::create('cms_csrf', $token)
            ->withSecure($c['secure'])
            ->withHttpOnly(false) // важно
            ->withSameSite('Strict')
            ->withPath('/')
            ->withDomain($c['domain'])
            ->withExpires(now()->addHours(12));

        return response()->json(['csrf' => $token])->withCookie($cookie);
    }
}
```

---

## Middleware проверки
```php
class VerifyApiCsrf
{
    private const METHODS = ['POST','PUT','PATCH','DELETE'];

    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->getMethod(), self::METHODS, true)) {
            return $next($request);
        }

        // при необходимости исключить /login и /refresh
        if ($request->is('api/v1/auth/login') || $request->is('api/v1/auth/refresh')) {
            return $next($request);
        }

        $header = (string) $request->header('X-CSRF-Token', '');
        $cookie = (string) $request->cookie('cms_csrf', '');

        if ($header === '' || $cookie === '' || ! hash_equals($cookie, $header)) {
            return response()->json(['message' => 'CSRF token mismatch'], 419);
        }
        return $next($request);
    }
}
```

Регистрация: добавить middleware в группу `api` или на конкретные роуты.

---

## Тесты
1. POST без CSRF → 419/403.
2. `GET /api/v1/auth/csrf` → получить cookie+токен; затем POST с заголовком `X-CSRF-Token` и cookie → 200.

---

## Приёмка
- [ ] Эндпоинт выдаёт `cms_csrf`.
- [ ] Middleware валидирует заголовок против cookie.
- [ ] Без токена → 419/403; с валидным → 200.
