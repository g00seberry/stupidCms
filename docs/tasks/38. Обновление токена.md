# Задача 38. Обновление токена: `POST /api/v1/auth/refresh`

## Резюме
Реализовать endpoint **одноразового обновления** токенов. По действующему **refresh JWT** из cookie `cms_rt`:
- выдать **новую пару** `access+refresh`;
- пометить старый refresh как использованный/отозванный (one‑time use);
- фиксировать повторное использование (reuse) и отклонять его.

**Критерии приёмки:** старый refresh не работает повторно (возвращает 401/409), новые токены устанавливаются в cookies.

Связанные: 36 (JWT‑модель), 37 (login), 39 (logout), 40 (CSRF cookie), 80 (аудит).

---

## Схема данных: `refresh_tokens`
- `id` BIGINT PK
- `user_id` BIGINT FK → users(id)
- `jti` CHAR(36) UNIQUE — из claims
- `kid` VARCHAR(20) — id ключа подписи
- `expires_at` DATETIME UTC
- `used_at` DATETIME NULL — одноразовое использование
- `revoked_at` DATETIME NULL — отзыв (logout/админ)
- `parent_jti` CHAR(36) NULL — чей предок в цепочке
- `created_at`/`updated_at`

Правила:
- Токен, имеющий `used_at` **или** `revoked_at`, недействителен.
- Чистилка удаляет истёкшие (cron).

---

## Контракт репозитория
```php
interface RefreshTokenRepository
{
    public function store(array $row): void; // user_id, jti, kid, expires_at, parent_jti?
    public function markUsed(string $jti): void;
    public function revoke(string $jti): void;
    public function find(string $jti): ?array; // used_at, revoked_at, user_id, expires_at
}
```

---

## Маршрут
```php
Route::post('/v1/auth/refresh', [RefreshController::class, 'refresh'])
    ->middleware(['throttle:refresh']); // + CSRF при необходимости
```

## Контроллер (пример)
```php
final class RefreshController
{
    public function __construct(
        private JwtService $jwt,
        private RefreshTokenRepository $repo,
    ) {}

    public function refresh(Request $request): JsonResponse
    {
        $rt = (string) $request->cookie(config('jwt.cookies.refresh'), '');
        if ($rt === '') return response()->json(['message' => 'Unauthorized'], 401);

        try {
            $verified = $this->jwt->verify($rt, 'refresh');
            $claims = $verified['claims'];
        } catch (Throwable $e) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $row = $this->repo->find($claims['jti']);
        if (! $row || $row['user_id'] != (int) $claims['sub']) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if ($row['used_at'] || $row['revoked_at'] || now('UTC')->gte($row['expires_at'])) {
            event(new RefreshTokenReuseDetected((int)$row['user_id'], $claims['jti']));
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // one-time
        $this->repo->markUsed($claims['jti']);

        // новая пара
        $uid = (int) $claims['sub'];
        $access = $this->jwt->issueAccessToken($uid);
        $newRefresh = $this->jwt->issueRefreshToken($uid, ['parent_jti' => $claims['jti']]);

        // сохранить новый refresh
        $decoded = $this->jwt->verify($newRefresh, 'refresh');
        $this->repo->store([
            'user_id' => $uid,
            'jti' => $decoded['claims']['jti'],
            'kid' => $decoded['kid'],
            'expires_at' => now('UTC')->addSeconds(config('jwt.refresh_ttl')),
            'parent_jti' => $claims['jti'],
        ]);

        return response()->json(['message' => 'ok'])
            ->withCookie(JwtCookies::access($access))
            ->withCookie(JwtCookies::refresh($newRefresh));
    }
}
```

---

## Тесты (Feature)
1. Валидный `cms_rt` → 200; в БД `used_at` выставлен у старого; новые cookies заданы.
2. Повтор с тем же `cms_rt` → 401 и без новых cookies.
3. Чужой/поддельный/истёкший refresh → 401.

---

## Приёмка
- [ ] Таблица/репозиторий refresh‑токенов.
- [ ] `POST /api/v1/auth/refresh` одноразовый.
- [ ] Повтор старого refresh → 401.
- [ ] Тесты зелёные.
```

