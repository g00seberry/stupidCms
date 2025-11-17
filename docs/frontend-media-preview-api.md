# Публичный API для preview медиа-файлов

## Изменения

Добавлен публичный эндпоинт для получения preview вариантов медиа-файлов без аутентификации.

## Эндпоинты

### Публичный preview (без аутентификации)

```
GET /api/v1/media/{id}/preview?variant={variant}
```

**Параметры:**

-   `id` (path) - ULID идентификатор медиа-файла
-   `variant` (query, опционально) - название варианта (по умолчанию: `thumbnail`)

**Пример:**

```
GET /api/v1/media/01ka95qq9tnv4vkc6wytgxssev/preview?variant=thumbnail
```

**Ответ:**

-   `200 OK` - файл напрямую (для локальных дисков)
-   `302 Redirect` - редирект на подписанный URL (для облачных дисков)

**Доступные варианты:**
Определяются в конфигурации `config/media.php` (обычно: `thumbnail`, `medium`, `large`).

### Админский preview (требует аутентификации)

```
GET /api/v1/admin/media/{media}/preview?variant={variant}
```

**Примечание:** Админский эндпоинт остаётся доступным для внутреннего использования.

## Использование

### В HTML

```html
<img
    src="/api/v1/media/01ka95qq9tnv4vkc6wytgxssev/preview?variant=thumbnail"
    alt="Preview"
/>
```

### В JavaScript/Fetch

```javascript
const mediaId = "01ka95qq9tnv4vkc6wytgxssev";
const variant = "thumbnail";
const url = `/api/v1/media/${mediaId}/preview?variant=${variant}`;

// Для <img> тега
const img = document.createElement("img");
img.src = url;

// Для fetch (получение URL после редиректа)
fetch(url, { redirect: "follow" })
    .then((response) => response.url)
    .then((finalUrl) => console.log("Final URL:", finalUrl));
```

## Важные моменты

1. **Без аутентификации** - публичный эндпоинт не требует JWT токена
2. **Rate limiting** - применяется стандартный лимит API (60 запросов/минуту)
3. **Генерация по требованию** - варианты генерируются синхронно при первом запросе
4. **TTL подписанных URL** - для облачных дисков используется `media.public_signed_ttl` (по умолчанию 1 час)
5. **Только изображения** - варианты поддерживаются только для медиа с `kind: 'image'`

## Миграция с админского API

**Было:**

```javascript
const url = `/api/v1/admin/media/${mediaId}/preview?variant=thumbnail`;
// Требовал: Authorization: Bearer {token}
```

**Стало:**

```javascript
const url = `/api/v1/media/${mediaId}/preview?variant=thumbnail`;
// Не требует аутентификации
```

## Ошибки

-   `404` - медиа не найдено или удалено
-   `422` - вариант не настроен в конфигурации
-   `500` - ошибка генерации варианта
-   `429` - превышен лимит запросов
