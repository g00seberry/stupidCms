# Задача 39. Logout: `POST /api/v1/auth/logout`

## Краткое описание функционала

Реализован endpoint для безопасного выхода из системы. При logout происходит:
- **Ревокация цепочки refresh токенов** (token family invalidation) для предотвращения reuse-атак
- **Очистка cookies** (access и refresh токены)
- **Поддержка `?all=1`** для отзыва всех refresh токенов пользователя на всех устройствах

**Основные характеристики:**

- **Маршрут**: `POST /api/v1/auth/logout`
- **Безопасность**: ревокация всей цепочки токенов (revokeFamily)
- **Идемпотентность**: работает даже без токена (просто очищает cookies)
- **Rate limiting**: 5 попыток в минуту (использует `throttle:login`)

**Ответы:**

- **200 OK**: успешный logout, cookies очищены

## Структура файлов

### Контроллеры

```
app/Http/Controllers/Auth/LogoutController.php - Контроллер для logout
```

### Роутинг

```
routes/api.php - Добавлен маршрут /api/v1/auth/logout
```

## Использование

### Стандартный logout

```bash
POST /api/v1/auth/logout
Cookie: cms_rt=eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Ответ (200 OK):**

```json
{
    "message": "Logged out successfully."
}
```

**Cookies:**
- `Set-Cookie: cms_at=...; expires=Thu, 01 Jan 1970 00:00:00 GMT` (очищен)
- `Set-Cookie: cms_rt=...; expires=Thu, 01 Jan 1970 00:00:00 GMT` (очищен)

**Изменения в БД:**
- Вся цепочка refresh токенов (токен и все его потомки) помечена как `revoked_at`

### Logout на всех устройствах

```bash
POST /api/v1/auth/logout?all=1
Cookie: cms_rt=eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Ответ (200 OK):**

```json
{
    "message": "Logged out successfully."
}
```

**Изменения в БД:**
- Вся цепочка refresh токенов помечена как `revoked_at`
- **Все остальные refresh токены пользователя** также помечены как `revoked_at`

### Logout без токена (идемпотентность)

```bash
POST /api/v1/auth/logout
# Без cookie cms_rt
```

**Ответ (200 OK):**

```json
{
    "message": "Logged out successfully."
}
```

Cookies очищаются даже если токен отсутствует или невалиден.

## Безопасность

### Token Family Invalidation

При logout отзывается **вся цепочка токенов** через `revokeFamily($jti)`. Это предотвращает:
- **Reuse attacks** после logout (если токен был перехвачен до logout)
- **Session fixation** (фиксация сессии)
- **Token theft** (кража токена)

### Logout All Devices

Параметр `?all=1` позволяет отозвать **все refresh токены пользователя** на всех устройствах. Это полезно при:
- Подозрении на компрометацию аккаунта
- Смене пароля
- Принудительном выходе со всех устройств

### Идемпотентность

Logout является идемпотентной операцией:
- Можно вызывать несколько раз подряд
- Работает даже без токена
- Не возвращает ошибки при отсутствии токена (лучший UX)

## API Reference

### LogoutController

#### `logout(Request $request): JsonResponse`

Обработать запрос на выход из системы.

**Параметры:**
- `$request` — HTTP запрос с опциональным cookie `cms_rt`
- `?all=1` — query параметр для отзыва всех токенов пользователя

**Возвращает:**
- **200 OK**: успешный logout с очищенными cookies

**Алгоритм:**
1. Извлечь refresh token из cookie `cms_rt`
2. Если токен отсутствует → очистить cookies и вернуть 200
3. Если токен невалиден → очистить cookies и вернуть 200 (без ошибки для UX)
4. Если токен валиден:
   - В транзакции:
     - Отозвать всю цепочку токенов через `revokeFamily($jti)`
     - Если `?all=1`: отозвать все токены пользователя
   - Очистить cookies
5. Вернуть 200 OK

## Тесты

**Файл:** `tests/Feature/AuthLogoutTest.php`

Покрытие (4 теста):

1. **Logout без токена**: проверка очистки cookies при отсутствии токена
2. **Logout с валидным токеном**: проверка ревокации цепочки токенов и очистки cookies
3. **Logout all**: проверка отзыва всех токенов пользователя при `?all=1`
4. **Logout с невалидным токеном**: проверка очистки cookies при невалидном токене (без ошибки)

## Список связанных задач

- **Задача 37**: Login endpoint - выпуск начальных токенов
- **Задача 38**: Token Refresh - одноразовое обновление токенов
- **Задача 40**: CSRF-cookie + заголовок - защита от CSRF атак

## Критерии приёмки (Definition of Done)

- [x] LogoutController реализован с ревокацией цепочки токенов
- [x] Поддержка `?all=1` для отзыва всех токенов пользователя
- [x] Cookies очищаются через `clearAccess()` и `clearRefresh()`
- [x] Идемпотентность: работает без токена
- [x] Маршрут `POST /api/v1/auth/logout` добавлен
- [x] Rate limiter настроен (5 попыток в минуту)
- [x] Тесты покрывают все сценарии
- [x] Документация создана

