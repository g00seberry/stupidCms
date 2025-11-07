# Задача 39. Выход: `POST /api/v1/auth/logout`

## Резюме
Logout для cookie‑based JWT:
- инвалидировать текущий refresh‑токен (по cookie `cms_rt`);
- очистить cookies `cms_at` и `cms_rt` у клиента;
- последующие защищённые запросы без новой авторизации → **401**.

Связанные: 36–38 (JWT/refresh), 40 (CSRF), 27 (политики).

---

## Маршрут
```php
Route::post('/v1/auth/logout', [LogoutController::class, 'logout'])
    ->middleware(['auth.api', 'throttle:20,1']); // auth.api — проверка access JWT
```

## Контроллер (пример)
```php
final class LogoutController
{
    public function __construct(private RefreshTokenRepository $repo) {}

    public function logout(Request $request): JsonResponse
    {
        $rt = (string) $request->cookie(config('jwt.cookies.refresh'), '');
        if ($rt !== '') {
            try {
                $verified = app(JwtService::class)->verify($rt, 'refresh');
                $this->repo->revoke($verified['claims']['jti']);
            } catch (Throwable $e) {/* ignore */}
        }

        return response()->json(['message' => 'ok'])
            ->withCookie(JwtCookies::forgetAccess())
            ->withCookie(JwtCookies::forgetRefresh());
    }
}
```

### Методы очистки cookies
```php
public static function forgetAccess(): Cookie { /* создать cookie с истёкшим сроком */ }
public static function forgetRefresh(): Cookie { /* создать cookie с истёкшим сроком */ }
```

---

## Тесты
1. Логин → cookies есть → `POST /logout` → 200 + cookies очищены.
2. Запрос к защищённому `/api/v1/me` после logout → **401**.
3. Повреждённый refresh в cookie — logout всё равно 200 и очищает cookies.

---

## Приёмка
- [ ] `/api/v1/auth/logout` отзывает refresh и чистит cookies.
- [ ] Дальнейшие защищённые запросы без авторизации → 401.
- [ ] Тесты зелёные.
