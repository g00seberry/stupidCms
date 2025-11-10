# Error Payload (contract)

> requires: docs:gen

## Формат ответа

- Базовая структура соответствует RFC 7807 и расширена обязательными полями `code`, `meta`, `trace_id`.
- Контракт описан классом `App\Support\Errors\ErrorPayload`.
- Любой ответ ошибки должен сериализоваться в JSON через `ErrorPayload::toArray()`.

```json
{
  "type": "https://stupidcms.dev/problems/unauthorized",
  "title": "Unauthorized",
  "status": 401,
  "code": "UNAUTHORIZED",
  "detail": "Authentication is required to access this resource.",
  "meta": {
    "request_id": "9f51ce7c-eed5-43b8-b7cb-6c30033f3f5e"
  },
  "trace_id": "00-9f51ce7ceed543b8b7cb6c30033f3f5e-9f51ce7ceed543b8-01"
}
```

## Поля

- `type` (`string`, URI) — уникальный идентификатор вида ошибки. Не должен быть пустым.
- `title` (`string`) — краткий заголовок.
- `status` (`int`) — HTTP-статус (100–599).
- `code` (`string`) — машинный код (`App\Support\Errors\ErrorCode::value`).
- `detail` (`string`) — человекочитаемое описание.
- `meta` (`object`) — словарь дополнительных параметров. Ключи — непустые строки, значения — скаляры/массивы/объекты (без ресурсов).
- `trace_id` (`string|null`) — идентификатор трассировки (W3C Trace Context). Поле присутствует всегда, может быть `null`.

## Коды ошибок

Перечень кода фиксирован в `App\Support\Errors\ErrorCode`:

| Код | Назначение |
| --- | --- |
| `BAD_REQUEST` | Некорректный запрос (400) |
| `UNAUTHORIZED` | Ошибка аутентификации (401) |
| `FORBIDDEN` | Нет прав (403) |
| `NOT_FOUND` | Ресурс не найден (404) |
| `VALIDATION_ERROR` | Ошибка валидации (422) |
| `CONFLICT` | Конфликт ресурса (409) |
| `RATE_LIMIT_EXCEEDED` | Превышен лимит запросов (429) |
| `SERVICE_UNAVAILABLE` | Временная недоступность сервиса (503) |
| `INTERNAL_SERVER_ERROR` | Непредвиденная ошибка (500) |
| `INVALID_OPTION_IDENTIFIER` | Некорректный идентификатор опции |
| `INVALID_OPTION_PAYLOAD` | Некорректное тело опции |
| `INVALID_JSON_VALUE` | Некорректное JSON-значение |
| `INVALID_OPTION_FILTERS` | Некорректные фильтры при запросе опций |
| `INVALID_PLUGIN_MANIFEST` | Ошибка манифеста плагина |
| `PLUGIN_ALREADY_DISABLED` | Плагин уже отключён |
| `PLUGIN_ALREADY_ENABLED` | Плагин уже включён |
| `PLUGIN_NOT_FOUND` | Плагин не найден |
| `ROUTES_RELOAD_FAILED` | Ошибка перезагрузки роутов |
| `MEDIA_IN_USE` | Медиа привязано и не может быть удалено |
| `MEDIA_DOWNLOAD_ERROR` | Ошибка скачивания медиа |
| `MEDIA_VARIANT_ERROR` | Ошибка генерации варианта медиа |
| `CSRF_TOKEN_MISMATCH` | Несовпадение CSRF токена |
| `JWT_ACCESS_TOKEN_MISSING` | Отсутствует access-token |
| `JWT_ACCESS_TOKEN_INVALID` | Невалидный access-token |
| `JWT_SUBJECT_INVALID` | Некорректный субъект токена |
| `JWT_USER_NOT_FOUND` | Пользователь не найден по токену |
| `JWT_AUTH_FAILURE` | Прочие ошибки авторизации JWT |

Расширение списка допускается только через обновление enum и этой таблицы.

## Использование

- Для построения ошибки используйте `ErrorPayload::create()` и передавайте в ErrorKernel.
- Дополнительные поля добавляются через `withMeta()`/`withAddedMeta()`.
- `trace_id` задаётся на стадии обогащения (middleware трассировки или ErrorKernel).


