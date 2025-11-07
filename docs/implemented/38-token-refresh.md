# Задача 38. Обновление токена: `POST /api/v1/auth/refresh`

## Краткое описание функционала

Реализован endpoint **одноразового обновления** JWT токенов. По действующему **refresh JWT** из cookie `cms_rt` выдаётся **новая пара** `access+refresh` токенов, при этом старый refresh токен помечается как использованный (one-time use) и отклоняется при повторном использовании.

**Основные характеристики:**

- **Маршрут**: `POST /api/v1/auth/refresh`
- **Безопасность**: одноразовое использование refresh токенов (one-time use)
- **Отслеживание**: фиксация повторного использования токенов (reuse detection)
- **Цепочка**: отслеживание родительских токенов через `parent_jti`
- **Rate limiting**: 10 попыток в минуту по хэшу `cookie+IP` (защита от NAT и автоматов)
- **Хранение**: все refresh токены записываются в БД с метаданными

**Ответы:**

- **200 OK**: успешное обновление с новыми cookies `cms_at` и `cms_rt`
- **401 Unauthorized**: отсутствие токена, невалидный токен, использованный/отозванный/истёкший токен (формат RFC 7807)
- **500 Internal Server Error**: ошибки инфраструктуры (БД, IO) (формат RFC 7807)

**Важно:** Все 401 ошибки очищают cookies (`clearAccess()` и `clearRefresh()`) для безопасности и улучшения UX.

**Кэширование:** Все auth endpoints используют middleware `no-cache-auth`, который добавляет `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` для предотвращения кэширования ответов с токенами.

## Структура файлов

### База данных

```
database/migrations/2025_11_07_150212_create_refresh_tokens_table.php - Миграция для таблицы refresh_tokens
database/migrations/2025_11_07_153542_add_meta_to_audits_table.php    - Миграция для добавления поля meta в audits
```

### Модели

```
app/Models/RefreshToken.php - Eloquent модель для refresh токенов
```

### Доменная логика

```
app/Domain/Auth/RefreshTokenRepository.php         - Интерфейс репозитория refresh токенов
app/Domain/Auth/RefreshTokenRepositoryImpl.php     - Реализация репозитория
app/Domain/Auth/RefreshTokenDto.php                - DTO для типобезопасного доступа к данным токена (readonly)
```

**Примечание:** Интерфейс содержит только `markUsedConditionally()` (атомарный метод) и `find(): ?RefreshTokenDto` для типобезопасности.

### Контроллеры и Middleware

```
app/Http/Controllers/Auth/RefreshController.php    - Контроллер для обновления токенов
app/Http/Controllers/Auth/LoginController.php      - Обновлён для сохранения refresh токенов в БД
app/Http/Controllers/Traits/Problems.php           - Trait для унифицированных RFC 7807 ответов
app/Http/Middleware/NoCacheAuth.php                - Middleware для добавления Cache-Control: no-store к auth endpoints
```

### Роутинг

```
routes/api.php                                     - Добавлен маршрут /api/v1/auth/refresh
app/Providers/RouteServiceProvider.php             - Добавлен rate limiter 'refresh'
```

### Провайдеры

```
app/Providers/AppServiceProvider.php               - Регистрация RefreshTokenRepository
```

### Команды

```
app/Console/Commands/CleanupExpiredRefreshTokens.php - Очистка истёкших refresh токенов
```

### Планировщик

```
routes/console.php                                - Настройка scheduled задач (очистка токенов)
```

**Примечание:** В Laravel 11 расписание определяется в `routes/console.php` через фасад `Schedule`, а не в `Console\Kernel`.

### Тесты

```
tests/Feature/AuthRefreshTest.php                  - Feature-тесты для refresh endpoint (15 тестов)
tests/Unit/RefreshTokenRepositoryTest.php          - Unit-тесты для контракта репозитория (11 тестов, 26 assertions)
```

**Покрытие контракта:**
- `find()` возвращает `RefreshTokenDto` или `null`
- `markUsedConditionally()` возвращает `1` для свежего токена, `0` для использованного/отозванного/истёкшего
- Атомарность: параллельные вызовы — только один возвращает `1`
- DTO методы: `isValid()`, `isInvalid()`

## Схема данных: `refresh_tokens`

**Таблица:** `refresh_tokens`

| Поле         | Тип           | Описание                                      |
|--------------|---------------|-----------------------------------------------|
| `id`         | BIGINT PK     | Первичный ключ                                |
| `user_id`    | BIGINT FK     | ID пользователя (→ users.id)                 |
| `jti`        | CHAR(36)      | JWT ID из claims (уникальный)                 |
| `kid`        | VARCHAR(20)   | Key ID из заголовка JWT                       |
| `expires_at` | DATETIME      | Время истечения токена (UTC)                  |
| `used_at`    | DATETIME NULL | Время использования (one-time use)            |
| `revoked_at` | DATETIME NULL | Время отзыва (logout/admin)                   |
| `parent_jti` | CHAR(36) NULL | JTI родительского токена в цепочке            |
| `created_at` | TIMESTAMP     | Время создания записи                         |
| `updated_at` | TIMESTAMP     | Время последнего обновления                   |

**Индексы:**
- `user_id` — для быстрого поиска токенов пользователя
- `expires_at` — для очистки истёкших токенов
- `(used_at, revoked_at)` — для проверки валидности токена
- `parent_jti` — для быстрых операций по цепочке токенов (отзыв семейства)
- `jti` — UNIQUE для предотвращения дубликатов

**Правила валидности:**
- Токен валиден, если `used_at IS NULL` AND `revoked_at IS NULL` AND `expires_at > NOW()`
- Токен, имеющий `used_at` или `revoked_at`, немедленно становится невалидным
- Чистилка удаляет истёкшие токены через cron (метод `deleteExpired()`)

## Использование

### Успешное обновление токенов

```bash
POST /api/v1/auth/refresh
Cookie: cms_rt=eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Ответ (200 OK):**

```json
{
    "message": "Tokens refreshed successfully."
}
```

**Cookies:**

- `Set-Cookie: cms_at=...; HttpOnly; Secure; SameSite=Strict` (новый access token)
- `Set-Cookie: cms_rt=...; HttpOnly; Secure; SameSite=Strict` (новый refresh token)

**Изменения в БД:**
- Старый refresh токен: `used_at` установлен на текущее время
- Новый refresh токен: создана запись с `parent_jti` = старый `jti`

### Попытка повторного использования

```bash
POST /api/v1/auth/refresh
Cookie: cms_rt=eyJ0eXAiOiJKV1QiLCJhbGc...  # Уже использованный токен
```

**Ответ (401 Unauthorized):**

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/problem+json

{
    "type": "about:blank",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Refresh token has been revoked or already used."
}
```

Cookies не устанавливаются.

### Отсутствие токена

```bash
POST /api/v1/auth/refresh
# Без cookie cms_rt
```

**Ответ (401 Unauthorized):**

```json
{
    "type": "about:blank",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Missing refresh token."
}
```

## Безопасность

### One-time use (одноразовое использование)

Каждый refresh токен может быть использован **только один раз**. После успешного обновления старый токен помечается как `used_at` и становится невалидным. Это защищает от:

- **Replay attacks** (повторное использование перехваченного токена)
- **Token theft** (кража токена)
- **Session fixation** (фиксация сессии)

### Отслеживание цепочки токенов

Каждый новый refresh токен содержит ссылку на родительский токен через поле `parent_jti`. Это позволяет:

- Отследить всю цепочку обновлений
- Идентифицировать подозрительную активность (например, несколько активных веток от одного токена)
- Отозвать все токены в цепочке при компрометации

**Token Family Invalidation (отзыв семейства токенов):**

При обнаружении попытки повторного использования токена (reuse attack), система автоматически отзывает **всю цепочку токенов** (токен и все его потомки). Это защищает от компрометации, когда злоумышленник перехватил старый токен и пытается его использовать повторно.

Метод `revokeFamily($jti)` рекурсивно находит и отзывает все токены в цепочке через `parent_jti`, предотвращая дальнейшее использование скомпрометированных токенов.

### Rate Limiting

Настроен rate limiter `refresh` в `RouteServiceProvider`:

- **Лимит**: 10 попыток в минуту
- **Ключ**: `hash('xxh128', cookie + IP)` — хэш комбинации refresh cookie и IP адреса
- **Цель**: защита от брутфорса и DoS атак
- **Преимущества**:
  - Избегает ложных блокировок за NAT (разные пользователи с одним IP имеют разные cookies)
  - Ловит автоматы (одинаковые куки с разных IP будут заблокированы)
  - Более точная идентификация клиента без дешифровки JWT

### Проверки валидности

Контроллер выполняет следующие проверки:

1. **Наличие токена**: cookie `cms_rt` должен присутствовать
2. **Верификация JWT**: токен должен быть валидным (подпись, issuer, audience, тип)
3. **Существование в БД**: токен должен быть найден в таблице `refresh_tokens`
4. **Соответствие user_id**: `user_id` в БД должен совпадать с `sub` в claims
5. **Не использован**: `used_at` должен быть `NULL`
6. **Не отозван**: `revoked_at` должен быть `NULL`
7. **Не истёк**: `expires_at` должен быть в будущем

Все проверки возвращают одинаковый 401 ответ без уточнения причины (blind validation).

### Защита от race condition (double-spend)

Для предотвращения гонки при параллельных запросах используется **транзакция** и **условное обновление**:

1. Вся операция refresh выполняется в `DB::transaction()`
2. Используется метод `markUsedConditionally()` вместо `markUsed()`:
   - Обновление происходит только если токен ещё валиден (`used_at IS NULL`, `revoked_at IS NULL`, `expires_at > NOW()`)
   - Возвращает количество затронутых строк (должно быть 1)
3. Если `updated !== 1`, это означает race condition или reuse-атаку — токен уже был использован между проверкой и обновлением
4. В этом случае вызывается `handleReuseAttack()`, который отзывает всю цепочку токенов

Это гарантирует, что даже при параллельных запросах только один из них сможет успешно обновить токен.

## Интеграция с Login endpoint

При успешном входе через `POST /api/v1/auth/login` (задача 37), refresh токен теперь **автоматически сохраняется** в БД:

```php
// В LoginController::login()
$refresh = $this->jwt->issueRefreshToken($user->getKey());

// Сохранить refresh token в БД (используем expires_at из claims['exp'])
$decoded = $this->jwt->verify($refresh, 'refresh');
$this->repo->store([
    'user_id' => $user->getKey(),
    'jti' => $decoded['claims']['jti'],
    'kid' => $decoded['kid'],
    'expires_at' => Carbon::createFromTimestampUTC($decoded['claims']['exp']),
    'parent_jti' => null, // Корневой токен
]);
```

**Важно:** `expires_at` берётся из `claims['exp']` JWT токена, а не вычисляется как `now() + ttl`. Это гарантирует синхронизацию с реальным временем истечения токена и предотвращает расхождения при изменении настроек `refresh_ttl`.

Первый токен в цепочке имеет `parent_jti = NULL`.

## API Reference

### RefreshController

#### `refresh(Request $request): JsonResponse`

Обновить токены по валидному refresh токену из cookie.

**Параметры:**
- `$request` — HTTP запрос с cookie `cms_rt`

**Возвращает:**
- **200 OK**: с новыми cookies `cms_at` и `cms_rt`
- **401 Unauthorized**: при любой ошибке (отсутствие токена, невалидный токен, использованный/отозванный/истёкший)

**Алгоритм:**
1. Извлечь refresh токен из cookie `cms_rt`
2. Верифицировать JWT (подпись, issuer, audience, тип)
3. Найти токен в БД по `jti`
4. Проверить `user_id`, `used_at`, `revoked_at`, `expires_at`
5. **В транзакции:**
   - Условно пометить старый токен как `used_at` (только если ещё валиден)
   - Проверить количество затронутых строк (должно быть 1)
   - Выпустить новую пару токенов
   - Сохранить новый refresh токен в БД с `parent_jti` и `expires_at` из `claims['exp']`
   - Логировать успешный refresh в `audits`
6. Вернуть ответ с новыми cookies

**При обнаружении reuse-атаки:**
- Вычислить `chain_depth` (глубину цепочки токенов)
- Отозвать всю цепочку токенов через `revokeFamily()` (атомарно в транзакции)
- Логировать событие `refresh_token_reuse` в `audits` с метаданными:
  - `jti` — ID переиспользованного токена
  - `chain_depth` — глубина цепочки
  - `revoked_count` — количество отозванных токенов
  - `timestamp` — время обнаружения атаки

### RefreshTokenRepository

#### `store(array $data): void`

Сохранить новый refresh токен в БД.

**Параметры:**
```php
[
    'user_id' => int,
    'jti' => string,
    'kid' => string,
    'expires_at' => Carbon,
    'parent_jti' => string|null,
]
```

#### `markUsed(string $jti): void`

Пометить токен как использованный (установить `used_at`).

#### `markUsedConditionally(string $jti): int`

Условно пометить токен как использованный (только если ещё валиден). Возвращает количество затронутых строк (0 или 1).

**Условия:**
- `used_at IS NULL`
- `revoked_at IS NULL`
- `expires_at > NOW()`

Используется для защиты от race condition при параллельных запросах.

#### `revoke(string $jti): void`

Отозвать токен (установить `revoked_at`).

#### `revokeFamily(string $jti): int`

Отозвать токен и все его потомки в цепочке (token family invalidation). Возвращает количество отозванных токенов.

**Важно:** Операция выполняется в транзакции для обеспечения атомарности. Все токены в цепочке отзываются одним атомарным действием.

Используется при обнаружении reuse-атаки для предотвращения дальнейшего использования скомпрометированных токенов.

#### `find(string $jti): ?RefreshTokenDto`

Найти токен по JTI. Возвращает `RefreshTokenDto` (readonly DTO) или `null` если токен не найден.

DTO обеспечивает типобезопасность и упрощает эволюцию схемы без изменения API репозитория.

#### `deleteExpired(): int`

Удалить истёкшие токены (где `expires_at < NOW()`). Возвращает количество удалённых токенов.

### RefreshToken Model

#### `isValid(): bool`

Проверить, валиден ли токен (не использован, не отозван, не истёк).

#### `isInvalid(): bool`

Проверить, невалиден ли токен (инверсия `isValid()`).

#### `user(): BelongsTo`

Получить пользователя, которому принадлежит токен.

## Тесты

**Файл:** `tests/Feature/AuthRefreshTest.php`

Покрытие (14 тестов):

1. **Успешное обновление**: проверка установки новых cookies, пометки старого токена как `used_at`, создания нового токена с `parent_jti`
2. **Повторное использование**: проверка 401 при повторной попытке использования уже использованного токена, проверка логирования reuse-атаки
3. **Отсутствие токена**: проверка 401 при отсутствии cookie `cms_rt`
4. **Невалидный токен**: проверка 401 при невалидном JWT
5. **Истёкший токен**: проверка 401 при истёкшем токене (expires_at в прошлом)
6. **Отозванный токен**: проверка 401 при отозванном токене (revoked_at не NULL)
7. **Цепочка токенов**: проверка отслеживания `parent_jti` через несколько обновлений
8. **Логирование refresh**: проверка записи успешных refresh в `audits`
9. **expires_at из claims**: проверка использования `expires_at` из `claims['exp']` вместо вычисления
10. **Token family invalidation**: проверка отзыва всей цепочки токенов при reuse-атаке
11. **500 на инфраструктурную ошибку**: проверка возврата 500 (не 401) при сбое БД, формат RFC 7807
12. **Cookie security attributes**: проверка HttpOnly, Secure, SameSite атрибутов cookies
13. **Metadata в аудите reuse**: проверка наличия jti, chain_depth, revoked_count в логе reuse-атаки
14. **Race condition (double refresh)**: проверка, что при двух последовательных запросах с одним токеном только один успешно обновит токен, второй вернёт 401

## Очистка истёкших токенов

Реализована Artisan команда и автоматическая очистка через планировщик:

**Команда:**

```bash
php artisan auth:cleanup-tokens
```

**Файл:** `app/Console/Commands/CleanupExpiredRefreshTokens.php`

Команда удаляет все токены, где `expires_at < NOW()`, и выводит количество удалённых записей.

**Планировщик:**

Команда автоматически запускается ежедневно в 02:00 через `routes/console.php` (стандартный подход Laravel 11):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('auth:cleanup-tokens')
    ->dailyAt('02:00')
    ->description('Clean up expired refresh tokens');
```

**Примечание:** В Laravel 11 расписание задач определяется в `routes/console.php`, а не в `Console\Kernel`. Это соответствует официальной документации Laravel 11.

Для работы планировщика необходимо настроить cron на сервере:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Аудит безопасности

Все операции с refresh токенами логируются в таблицу `audits`:

- **Успешный refresh**: `action='refresh'`, `user_id` = ID пользователя
- **Reuse-атака**: `action='refresh_token_reuse'`, `user_id` = ID пользователя, с детальными метаданными

В обоих случаях записываются:
- `ip` — IP адрес клиента
- `ua` — User-Agent
- `subject_type` — `App\Models\User`
- `subject_id` — ID пользователя

**Дополнительные метаданные для reuse-атак** (поле `meta` в формате JSON):
- `jti` — JWT ID переиспользованного токена
- `chain_depth` — глубина цепочки токенов (расстояние от корневого токена)
- `revoked_count` — количество отозванных токенов в результате attack (включая потомков)
- `timestamp` — время обнаружения атаки в UTC

Эти метаданные критичны для расследований: они позволяют понять масштаб компрометации, определить момент утечки токена и оценить количество затронутых сессий.

Аудит позволяет отслеживать подозрительную активность и расследовать инциденты безопасности.

## Список связанных задач

- **Задача 36**: Cookie-based JWT модель токенов - базовая инфраструктура JWT
- **Задача 37**: Login endpoint - выпуск и сохранение начальных токенов
- **Задача 39**: Logout/Rotate - отзыв refresh токенов при выходе
- **Задача 40**: CSRF-cookie + заголовок - защита от CSRF атак
- **Задача 80**: Audit - аудит использования и попыток повторного использования токенов

## Критерии приёмки (Definition of Done)

- [x] Таблица `refresh_tokens` создана с необходимыми полями и индексами
- [x] RefreshToken модель создана с методами `isValid()`, `isInvalid()`, `user()`
- [x] RefreshTokenRepository интерфейс и реализация созданы
- [x] RefreshTokenRepository зарегистрирован в AppServiceProvider
- [x] LoginController обновлён для сохранения refresh токенов в БД
- [x] RefreshController реализован с one-time use логикой
- [x] Маршрут `POST /api/v1/auth/refresh` добавлен
- [x] Rate limiter 'refresh' настроен (10 попыток в минуту)
- [x] Старый refresh токен помечается как `used_at` после обновления
- [x] Новый refresh токен содержит `parent_jti` для отслеживания цепочки
- [x] Повторное использование токена возвращает 401
- [x] Тесты покрывают все сценарии (успех, reuse, невалидные токены, истёкшие, отозванные)
- [x] Формат ошибок соответствует RFC 7807 (problem+json)
- [x] Документация создана

## Реализованные улучшения безопасности

### Критичные фиксы (реализованы)

1. ✅ **Race condition protection**: Транзакция + условное обновление предотвращает double-spend при параллельных запросах
2. ✅ **Token family invalidation**: При обнаружении reuse-атаки отзывается вся цепочка токенов (с транзакцией)
3. ✅ **expires_at синхронизация**: Использование `claims['exp']` вместо вычисления предотвращает расхождения
4. ✅ **Индекс parent_jti**: Быстрый поиск потомков для отзыва цепочки
5. ✅ **Аудит безопасности**: Логирование всех refresh операций и reuse-атак с детальными метаданными (jti, chain_depth, revoked_count)
6. ✅ **Автоматическая очистка**: Ежедневная очистка истёкших токенов через планировщик
7. ✅ **Разделение 401/500 ошибок**: Domain-ошибки (невалидный токен) → 401, инфраструктурные (БД) → 500
8. ✅ **Улучшенный rate limiter**: Использование хэша `cookie+IP` вместо только IP для избежания NAT-проблем
9. ✅ **Унифицированный RFC 7807 trait**: Trait `Problems` для единообразных problem+json ответов
10. ✅ **Cookie-политики из конфига**: SameSite, Secure, Domain конфигурируются через .env (JWT_SAMESITE, etc.)
11. ✅ **DTO вместо массива**: `find()` возвращает типобезопасный `RefreshTokenDto` для упрощения эволюции схемы
12. ✅ **Убран публичный markUsed()**: Только `markUsedConditionally()` для предотвращения race conditions
13. ✅ **Расписание в routes/console.php**: Планировщик определён в `routes/console.php` через фасад `Schedule` (стандартный подход Laravel 11)
14. ✅ **Очистка cookies при 401**: Все ошибки 401 в refresh endpoint очищают cookies для безопасности и улучшения UX
15. ✅ **Fallback хэш-алгоритм**: Rate limiter автоматически использует sha256 если xxh128 недоступен
16. ✅ **Cache-Control: no-store**: Middleware `NoCacheAuth` добавляет заголовок для всех auth endpoints (предотвращает кэширование прокси)
17. ✅ **Unit-тесты репозитория**: 11 тестов проверяют контракт интерфейса (типы, атомарность, бизнес-логику)

## Будущие улучшения

1. **CTE-оптимизация revokeFamily()**: Использование рекурсивного CTE (MySQL 8.0+/PostgreSQL) для одно-запросной ревокации цепочки токенов (см. комментарий в коде)
2. **Event при reuse**: Диспатч события `RefreshTokenReuseDetected` для мониторинга и алертов
3. **Metrics**: Отслеживание метрик (количество обновлений, reuse попыток, истёкших токенов)
4. **Redis cache**: Кэширование валидных токенов для ускорения проверок

