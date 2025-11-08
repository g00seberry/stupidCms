# T-044 — ETag/Last-Modified middleware

```yaml
id: T-044
title: Включить ETag/Last-Modified для GET/HEAD с поддержкой 304
area: [backend, laravel, api]
priority: P1
size: S
depends_on: []
blocks: []
labels: [stupidCms, mvp, http-cache]
```

## 1) Контекст

Цель — снизить сетевой трафик и ускорить отдачу неизменённых ресурсов за счёт условных запросов HTTP.  
Middleware должен автоматически:

- Генерировать `ETag` по хэшу тела ответа (strong ETag).
- Поддерживать заголовки `If-None-Match` и `If-Modified-Since`, корректно возвращая `304 Not Modified` без тела.
- (Опционально) Проставлять/пробрасывать `Last-Modified`, если контроллер/ресурс это сообщил.

**Ограничения проекта:** Laravel 12.x, PHP 8.2+, JSON/HTML API, RFC 9110, ответы об ошибках — RFC7807, ResponseCache — только для гостей, плоские URL.  
**Вне скоупа:** полноценный CDN/Reverse proxy, генерация `Last-Modified` из моделей «автоматом» (только если явно указано контроллером/хедером).

## 2) Требуемый результат (Deliverables)

- **Код:**
  - `app/Http/Middleware/HttpConditionalCache.php` — основная логика.
  - `app/Support/HttpCache/EtagHasher.php` — сервис хеширования (sha256 → hex).
  - `app/Support/HttpCache/LastModifiedResolver.php` — извлечение/нормализация значения `Last-Modified` (по хедеру/атрибуту).
  - `config/http_cache.php` — флаги, настройки Vary и алгоритма.
  - `app/Providers/HttpCacheServiceProvider.php` — регистрация респонс-макроса `response()->lastModified(Carbon|DateTime|string)`.
  - Обновление `app/Http/Kernel.php` — подключить middleware в `web` и `api` группы после кэш/метрик.
- **Тесты:**
  - `tests/Unit/HttpCache/EtagHasherTest.php` — корректность генерации ETag.
  - `tests/Feature/HttpCache/ConditionalGetTest.php` — сценарии 200→304 (GET/HEAD), приоритет `If-None-Match` над `If-Modified-Since`, пропуски для Streaming/Binary.
- **Документация:**
  - `docs/http-caching.md` — как это работает, как отключить, как проставить `Last-Modified` из контроллера.
- **Команды проверки:**
  - `php artisan config:clear && phpunit --testsuite Unit,Feature`
  - `curl` примеры см. ниже.

## 3) Функциональные требования

- Применяется **только** к `GET` и `HEAD`.
- Обрабатываются ответы со статусами из конфига (по умолчанию `200`) и с типами контента: `text/*`, `application/json`, `application/problem+json`, `application/javascript`, `application/xml`.
- **Исключения:** `StreamedResponse`, `BinaryFileResponse`, ответы с `Content-Disposition: attachment`, ответы с `Cache-Control: no-store` — пропуск.
- Генерация `ETag`:
  - strong ETag по `sha256(body)` в hex, формат: `"${hex}"` (в кавычках).
  - Не пересчитывать повторно, если сервер уже проставил `ETag` ранее.
- `Last-Modified`:
  - Если в ответе уже есть `Last-Modified` — не менять.
  - Если есть служебный хедер `X-Last-Modified` (RFC3339 или unix timestamp) — сконвертировать в формат HTTP-date (RFC 7231) и заменить на `Last-Modified`.
  - Макрос `response()->lastModified($ts)` добавляет правильный хедер в ответ.
- Условная логика:
  - Если запрос содержит `If-None-Match` и он **совпадает** с текущим `ETag` (без/с кавычками) — вернуть `304` **без тела**, оставить `ETag`, а также пробросить совместимые заголовки (`Cache-Control`, `Expires`, `Vary`, `Last-Modified` если был).
  - Если `If-None-Match` **нет**, но есть валидный `If-Modified-Since` и он **не раньше** текущего `Last-Modified` — вернуть `304` (аналогично).
  - При наличии **оба** — приоритет `If-None-Match` (RFC 9110).
- Заголовок `Vary`:
  - Объединять с имеющимся и добавлять значения из конфига (по умолчанию: `Accept, Accept-Encoding, Accept-Language, Cookie`).
- Поведение `HEAD`:
  - Семантика как у `GET`, тело **никогда** не отправляется; 304 условия те же.
- Ошибки (RFC7807) **не** хешировать, если `Cache-Control: no-store` или статус не из whitelist.

## 4) Нефункциональные требования

- **Производительность:** вычислять хэш один раз после формирования тела; не трогать большие стримы/файлы.
- **Безопасность/приватность:** ETag генерируется из тела ответа. Для персонализированных ответов убедиться, что выставлен `Cache-Control: private`; `Vary: Cookie` присутствует по умолчанию.
- **Совместимость:** Laravel 12.x, PHP 8.2+; без сторонних пакетов.
- **Надёжность:** не изменять статус/тело за исключением переводов 200→304 при совпадении валидаторов.

## 5) Контракты API

Новых эндпоинтов нет. Поведение условных запросов для существующих `GET/HEAD`:
- Клиент может послать `If-None-Match: "<etag>"` и получить `304`, если ресурс не изменился.
- Клиент может послать `If-Modified-Since: <HTTP-date>` (при наличии `Last-Modified`).

## 6) Схема БД

Н/Д.

## 7) План реализации (для ИИ)

1. Создать `config/http_cache.php` с флагами и списками `methods`, `status_codes`, `vary`, `skip_if` и настройками ETag/Last-Modified.
2. Реализовать `app/Support/HttpCache/EtagHasher.php` (`hash(string $payload): string`).
3. Реализовать `app/Support/HttpCache/LastModifiedResolver.php` (парсинг/нормализация/подстановка хедера).
4. Реализовать middleware `HttpConditionalCache`:
   - Пропуск нерелевантных запросов/ответов.
   - Выставление `ETag`/`Last-Modified` (если ещё не были).
   - Оценка `If-None-Match` / `If-Modified-Since` и возврат `304` при совпадении.
   - Управление `Vary`.
5. Добавить провайдер `HttpCacheServiceProvider` с макросом `Response::macro('lastModified', ...)`; зарегистрировать в `config/app.php`.
6. Подключить middleware в `Kernel.php` (web/api группы).
7. Написать unit/feature тесты.
8. Обновить `docs/http-caching.md` и добавить `curl` примеры.

## 8) Acceptance Criteria

- [ ] Первый `GET` ресурса возвращает `200` и содержит валидный `ETag` (и `Last-Modified`, если установлен контроллером).
- [ ] Повторный `GET` с `If-None-Match` равным текущему `ETag` возвращает `304` **без тела** и с заголовками `ETag`, `Vary` (и `Last-Modified`, если был).
- [ ] Повторный `GET` с `If-Modified-Since` не ранее `Last-Modified` возвращает `304` **без тела**.
- [ ] `HEAD` ведёт себя как `GET` в части валидаторов, тело не отдается.
- [ ] `StreamedResponse`/`BinaryFileResponse` и `attachment` не изменяются middleware.
- [ ] RFC7807-ошибки со статусами вне whitelist или с `no-store` не хешируются.
- [ ] Все тесты зелёные; линтеры без диффов.

## 9) Роллаут / Бэкаут

**Роллаут:**
1. Деплой кода.
2. `php artisan config:cache && php artisan route:cache`.
3. Включить флаг `http_cache.enabled=true` (по умолчанию true).

**Бэкаут:**
- `http_cache.enabled=false` в `.env` (или убрать middleware из `Kernel.php`).
- Никаких миграций нет.

**Риски и меры:**
- Некорректные 304 для персонализированных ответов → гарантировать `private` и `Vary: Cookie`.
- Производительность на больших ответах → автоматический пропуск стримов/файлов; один проход хеша.

## 10) Формат ответа от нейросети (для задачи разработки)

ИИ должен вернуть:
1. **Plan** — пошаговый план (8–10 шагов).  
2. **Files** — список путей.  
3. **Patchset** — полные файлы или unified-diff.  
4. **Tests** — код тестов + команды.  
5. **Checks** — команды валидации и `curl` сценарии.  
6. **Notes** — спорные моменты и дефолты.

---

### Примеры `curl`

Первичный запрос (получение ETag):
```bash
curl -i https://example.com/api/pages/home
# ... HTTP/1.1 200 OK
# ETag: "c2a1e7..."
# Last-Modified: Mon, 03 Nov 2025 12:00:00 GMT
# Vary: Accept, Accept-Encoding, Accept-Language, Cookie
```

Условный запрос по ETag:
```bash
curl -i -H 'If-None-Match: "c2a1e7..."' https://example.com/api/pages/home
# HTTP/1.1 304 Not Modified
# ETag: "c2a1e7..."
# Vary: Accept, Accept-Encoding, Accept-Language, Cookie
# (тела нет)
```

Условный запрос по Last-Modified:
```bash
curl -i -H 'If-Modified-Since: Mon, 03 Nov 2025 12:00:00 GMT' https://example.com/api/pages/home
# HTTP/1.1 304 Not Modified
# Last-Modified: Mon, 03 Nov 2025 12:00:00 GMT
```

### Замечания по реализации

- ETag считается **после** формирования тела, до отправки ответа.
- При наличии обоих валидаторов (`If-None-Match` и `If-Modified-Since`) — использовать только сравнение по `ETag`.
- Формат даты: RFC 7231 (пример: `Mon, 03 Nov 2025 12:00:00 GMT`).

