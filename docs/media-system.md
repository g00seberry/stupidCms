# Система медиафайлов

Подробная документация по системе управления медиафайлами в headless CMS.

## Содержание

1. [Обзор](#обзор)
2. [Архитектура](#архитектура)
3. [Модели данных](#модели-данных)
4. [API эндпоинты](#api-эндпоинты)
5. [Загрузка файлов](#загрузка-файлов)
6. [Варианты изображений](#варианты-изображений)
7. [Хранилище и файловая система](#хранилище-и-файловая-система)
8. [Конфигурация](#конфигурация)
9. [Авторизация и политики доступа](#авторизация-и-политики-доступа)

---

## Обзор

Система медиафайлов обеспечивает:

-   Загрузку и хранение файлов (изображения, видео, аудио, документы)
-   Автоматическое извлечение метаданных (размеры, EXIF, длительность)
-   Генерацию вариантов изображений (thumbnails, resized)
-   Связывание медиа с записями контента (Entry)
-   Мягкое удаление с проверкой использования
-   Поддержку локальных и облачных дисков (S3)
-   Подписанные URL для безопасного доступа

### Поддерживаемые типы файлов

-   **Изображения**: JPEG, PNG, WebP, GIF
-   **Видео**: MP4
-   **Аудио**: MPEG
-   **Документы**: PDF

Настройка через `config/media.php` → `allowed_mimes`.

---

## Архитектура

### Компоненты

```
┌─────────────────────────────────────────────────────────────┐
│                     HTTP Layer                               │
├─────────────────────────────────────────────────────────────┤
│  MediaController         MediaPreviewController              │
│  ├─ index()            ├─ preview()                         │
│  ├─ store()            ├─ download()                        │
│  ├─ show()                                                      │
│  ├─ update()                                                   │
│  ├─ destroy()                                                  │
│  └─ restore()                                                  │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                  Domain Layer                                │
├─────────────────────────────────────────────────────────────┤
│  MediaStoreAction          OnDemandVariantService            │
│  ├─ execute()           ├─ ensureVariant()                  │
│  └─ storeFile()         ├─ generateVariant()                │
│                         └─ resizeImage()                    │
│                                                              │
│  MediaMetadataExtractor   GenerateVariantJob                 │
│  ├─ extract()           └─ handle()                         │
│  ├─ readExif()                                                │
│  └─ canReadExif()                                            │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    Data Layer                                │
├─────────────────────────────────────────────────────────────┤
│  Media           MediaVariant                                 │
│  └─ variants()   └─ media()                                   │
└─────────────────────────────────────────────────────────────┘
```

### Поток загрузки

1. `POST /api/v1/admin/media` → `MediaController@store`
2. Валидация → `StoreMediaRequest`
3. Сохранение → `MediaStoreAction@execute`
    - Сохранение файла на диск
    - Извлечение метаданных → `MediaMetadataExtractor`
    - Вычисление SHA256 checksum
    - Создание записи `Media`
4. Ответ → `MediaResource` (JSON)

---

## Модели данных

### Media

**Таблица**: `media`

**Первичный ключ**: ULID (строка)

**Поля**:

| Поле              | Тип            | Описание                                                 |
| ----------------- | -------------- | -------------------------------------------------------- |
| `id`              | `ulid`         | ULID идентификатор (PK)                                  |
| `disk`            | `string(32)`   | Имя диска (`media`, `s3`)                                |
| `path`            | `string`       | Путь к файлу на диске (unique)                           |
| `original_name`   | `string`       | Оригинальное имя файла                                   |
| `ext`             | `string(16)`   | Расширение файла (nullable)                              |
| `mime`            | `string(120)`  | MIME-тип файла                                           |
| `size_bytes`      | `bigint`       | Размер файла в байтах                                    |
| `width`           | `unsigned int` | Ширина (для изображений/видео, nullable)                 |
| `height`          | `unsigned int` | Высота (для изображений/видео, nullable)                 |
| `duration_ms`     | `unsigned int` | Длительность в миллисекундах (для видео/аудио, nullable) |
| `checksum_sha256` | `string(64)`   | SHA256 checksum (nullable, indexed)                      |
| `exif_json`       | `json`         | EXIF метаданные (для изображений, nullable)              |
| `title`           | `string`       | Пользовательский заголовок (nullable)                    |
| `alt`             | `string`       | Alt-текст для изображений (nullable)                     |
| `collection`      | `string(64)`   | Коллекция/категория (nullable, indexed)                  |
| `created_at`      | `timestamp`    | Дата создания (indexed)                                  |
| `updated_at`      | `timestamp`    | Дата обновления                                          |
| `deleted_at`      | `timestamp`    | Дата мягкого удаления (nullable, indexed)                |

**Методы**:

-   `kind(): string` — определяет тип медиа по MIME: `'image'`, `'video'`, `'audio'`, `'document'`

**Связи**:

-   `variants(): HasMany<MediaVariant>` — варианты изображения (thumbnails, resized)

**Файл**: `app/Models/Media.php`

---

### MediaVariant

**Таблица**: `media_variants`

**Первичный ключ**: ULID (строка)

**Поля**:

| Поле         | Тип            | Описание                                        |
| ------------ | -------------- | ----------------------------------------------- |
| `id`         | `ulid`         | ULID идентификатор (PK)                         |
| `media_id`   | `ulid`         | ID исходного медиа-файла (FK, cascade delete)   |
| `variant`    | `string(32)`   | Название варианта (`thumbnail`, `medium`, etc.) |
| `path`       | `string`       | Путь к файлу варианта на диске (unique)         |
| `width`      | `unsigned int` | Ширина варианта (nullable)                      |
| `height`     | `unsigned int` | Высота варианта (nullable)                      |
| `size_bytes` | `bigint`       | Размер файла варианта в байтах                  |
| `created_at` | `timestamp`    | Дата создания                                   |
| `updated_at` | `timestamp`    | Дата обновления                                 |

**Индексы**:

-   Unique: `[media_id, variant]` — один вариант на медиа-файл

**Связи**:

-   `media(): BelongsTo<Media>` — исходный медиа-файл

**Файл**: `app/Models/MediaVariant.php`

---

## API эндпоинты

Все эндпоинты находятся под префиксом `/api/v1/admin/media` и требуют аутентификации.

### Список медиафайлов

**`GET /api/v1/admin/media`**

**Параметры запроса**:

| Параметр     | Тип      | Описание                                                                    |
| ------------ | -------- | --------------------------------------------------------------------------- |
| `q`          | `string` | Поиск по `title` и `original_name` (max 255)                                |
| `kind`       | `enum`   | Фильтр по типу: `image`, `video`, `audio`, `document`                       |
| `mime`       | `string` | Фильтр по MIME (prefix match, max 120)                                      |
| `collection` | `string` | Фильтр по коллекции (max 64)                                                |
| `deleted`    | `enum`   | Управление soft-deleted: `with`, `only`                                     |
| `sort`       | `enum`   | Поле сортировки: `created_at`, `size_bytes`, `mime` (default: `created_at`) |
| `order`      | `enum`   | Направление: `asc`, `desc` (default: `desc`)                                |
| `per_page`   | `int`    | Размер страницы (1-100, default: 15)                                        |
| `page`       | `int`    | Номер страницы (min: 1)                                                     |

**Ответ**: `200 OK`

```json
{
    "data": [
        {
            "id": "01HXYZ...",
            "kind": "image",
            "name": "hero.jpg",
            "ext": "jpg",
            "mime": "image/jpeg",
            "size_bytes": 235678,
            "width": 1920,
            "height": 1080,
            "duration_ms": null,
            "title": "Hero image",
            "alt": "Hero cover",
            "collection": "uploads",
            "created_at": "2025-01-10T12:00:00+00:00",
            "updated_at": "2025-01-10T12:00:00+00:00",
            "deleted_at": null,
            "preview_urls": {
                "thumbnail": "https://api.stupidcms.dev/api/v1/admin/media/01HXYZ.../preview?variant=thumbnail",
                "medium": "https://api.stupidcms.dev/api/v1/admin/media/01HXYZ.../preview?variant=medium"
            },
            "download_url": "https://api.stupidcms.dev/api/v1/admin/media/01HXYZ.../download"
        }
    ],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1
    }
}
```

**Коды ответов**: `200`, `401`, `422`, `429`

**Файл**: `app/Http/Controllers/Admin/V1/MediaController.php@index`

---

### Загрузка медиафайла

**`POST /api/v1/admin/media`**

**Параметры тела**:

| Параметр     | Тип      | Обязательный | Описание                                                                                      |
| ------------ | -------- | ------------ | --------------------------------------------------------------------------------------------- |
| `file`       | `file`   | ✅           | Файл (MIME из `config('media.allowed_mimes')`, max размер из `config('media.max_upload_mb')`) |
| `title`      | `string` | ❌           | Пользовательский заголовок (max 255)                                                          |
| `alt`        | `string` | ❌           | Alt-текст для изображений (max 255)                                                           |
| `collection` | `string` | ❌           | Коллекция/категория (max 64, regex: `^[a-z0-9-_.]+$`)                                         |

**Пример запроса** (multipart/form-data):

```
POST /api/v1/admin/media
Content-Type: multipart/form-data

file: <binary>
title: Hero image
alt: Hero cover
collection: uploads
```

**Ответ**: `201 Created`

```json
{
    "data": {
        "id": "01HXYZ...",
        "kind": "image",
        "name": "hero.jpg",
        "ext": "jpg",
        "mime": "image/jpeg",
        "size_bytes": 235678,
        "width": 1920,
        "height": 1080,
        "duration_ms": null,
        "title": "Hero image",
        "alt": "Hero cover",
        "collection": "uploads",
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00",
        "deleted_at": null,
        "preview_urls": {
            "thumbnail": "...",
            "medium": "..."
        },
        "download_url": "..."
    }
}
```

**Коды ответов**: `201`, `401`, `422`, `429`

**Файл**: `app/Http/Controllers/Admin/V1/MediaController.php@store`

---

### Просмотр медиафайла

**`GET /api/v1/admin/media/{media}`**

**Параметры пути**:

| Параметр | Тип      | Описание         |
| -------- | -------- | ---------------- |
| `media`  | `string` | ULID медиа-файла |

**Ответ**: `200 OK` (аналогично загрузке)

**Коды ответов**: `200`, `401`, `404`, `429`

**Файл**: `app/Http/Controllers/Admin/V1/MediaController.php@show`

---

### Обновление медиафайла

**`PUT /api/v1/admin/media/{media}`**

**Параметры тела** (все опциональны):

| Параметр     | Тип      | Описание                                          |
| ------------ | -------- | ------------------------------------------------- |
| `title`      | `string` | Новый заголовок (max 255)                         |
| `alt`        | `string` | Новый alt-текст (max 255)                         |
| `collection` | `string` | Новая коллекция (max 64, regex: `^[a-z0-9-_.]+$`) |

**Ответ**: `200 OK` (аналогично загрузке)

**Коды ответов**: `200`, `401`, `404`, `422`, `429`

**Файл**: `app/Http/Controllers/Admin/V1/MediaController.php@update`

---

### Удаление медиафайла (soft delete)

**`DELETE /api/v1/admin/media/{media}`**

**Поведение**:

-   Выполняет мягкое удаление (`softDeletes`)
-   Медиа можно удалять независимо от использования в контенте (медиа привязаны через fields)

**Ответ**: `204 No Content`

**Коды ответов**: `204`, `401`, `404`, `429`

**Файл**: `app/Http/Controllers/Admin/V1/MediaController.php@destroy`

---

### Восстановление удалённого медиафайла

**`POST /api/v1/admin/media/{media}/restore`**

**Ответ**: `200 OK` (аналогично загрузке)

**Коды ответов**: `200`, `401`, `404`, `429`

**Файл**: `app/Http/Controllers/Admin/V1/MediaController.php@restore`

---

### Предпросмотр варианта изображения

**`GET /api/v1/admin/media/{media}/preview?variant={variant}`**

**Параметры пути**:

| Параметр | Тип      | Описание         |
| -------- | -------- | ---------------- |
| `media`  | `string` | ULID медиа-файла |

**Параметры запроса**:

| Параметр  | Тип      | Описание                                                         |
| --------- | -------- | ---------------------------------------------------------------- |
| `variant` | `string` | Имя варианта (`thumbnail`, `medium`, etc., default: `thumbnail`) |

**Поведение**:

-   Генерирует вариант по требованию, если отсутствует
-   Для локального диска → возвращает файл напрямую (`200 OK`, `Content-Type: image/*`)
-   Для облачных дисков (S3) → редирект на подписанный URL (`302 Found`)

**Ответ**: `200 OK` (локальный) или `302 Found` (облачный)

**Коды ответов**: `200`, `302`, `401`, `404`, `422`, `429`, `500`

**Файл**: `app/Http/Controllers/Admin/V1/MediaPreviewController.php@preview`

---

### Скачивание оригинала

**`GET /api/v1/admin/media/{media}/download`**

**Поведение**:

-   Аналогично предпросмотру, но для оригинального файла

**Ответ**: `200 OK` (локальный) или `302 Found` (облачный)

**Коды ответов**: `200`, `302`, `401`, `404`, `429`, `500`

**Файл**: `app/Http/Controllers/Admin/V1/MediaPreviewController.php@download`

---

## Загрузка файлов

### Процесс загрузки

1. **Валидация** (`StoreMediaRequest`):

    - Проверка наличия файла
    - Проверка MIME-типа (из `config('media.allowed_mimes')`)
    - Проверка размера (из `config('media.max_upload_mb')`)

2. **Сохранение** (`MediaStoreAction@execute`):

    - Определение MIME-типа
    - Вычисление SHA256 checksum
    - Сохранение файла на диск с уникальным именем (ULID)
    - Организация пути (стратегия: `by-date` или `hash-shard`)

3. **Извлечение метаданных** (`MediaMetadataExtractor@extract`):

    - Для изображений: размеры (`getimagesize`), EXIF данные
    - Для видео/аудио: длительность (не реализовано)

4. **Создание записи** (`Media::create`):
    - Сохранение всех метаданных в БД

**Файл**: `app/Domain/Media/Actions/MediaStoreAction.php`

---

### Стратегии организации путей

#### `by-date` (по умолчанию)

Формат: `YYYY/MM/DD/{ulid}.{ext}`

Пример: `2025/01/10/01hxyz123abc.jpg`

**Плюсы**:

-   Логичная структура по датам
-   Удобно для резервного копирования по периодам

#### `hash-shard`

Формат: `{first-2-chars-of-checksum}/{next-2-chars}/{ulid}.{ext}`

Пример: `a1/b2/01hxyz123abc.jpg`

**Плюсы**:

-   Равномерное распределение файлов по директориям
-   Оптимально для больших объёмов файлов

**Настройка**: `config/media.php` → `path_strategy` или `MEDIA_PATH_STRATEGY`

---

### Извлечение метаданных

**Сервис**: `MediaMetadataExtractor`

**Метод**: `extract(UploadedFile $file, ?string $mime): array`

**Возвращает**:

```php
[
    'width' => ?int,           // Ширина (для изображений)
    'height' => ?int,          // Высота (для изображений)
    'duration_ms' => ?int,     // Длительность (для видео/аудио, не реализовано)
    'exif' => ?array,          // EXIF данные (для JPEG/TIFF)
]
```

**EXIF данные**:

-   Поддерживаются только для JPEG и TIFF
-   Требуется расширение PHP `exif`
-   Нормализуются (только скалярные значения)

**Файл**: `app/Domain/Media/Services/MediaMetadataExtractor.php`

---

## Варианты изображений

### Конфигурация

Варианты настраиваются в `config/media.php`:

```php
'variants' => [
    'thumbnail' => ['max' => 320],   // Максимальная сторона 320px
    'medium' => ['max' => 1024],     // Максимальная сторона 1024px
],
```

### Генерация по требованию

**Сервис**: `OnDemandVariantService`

**Метод**: `ensureVariant(Media $media, string $variant): MediaVariant`

**Поведение**:

1. Проверяет существование варианта в БД и на диске
2. Если отсутствует → генерирует синхронно (или через job)
3. Возвращает существующий или созданный вариант

**Процесс генерации**:

1. Загрузка оригинального изображения с диска
2. Создание ресурса GD из содержимого
3. Вычисление целевых размеров (пропорциональное масштабирование)
4. Изменение размера через `imagecopyresampled` (с сохранением прозрачности)
5. Кодирование в исходный формат (PNG, GIF, WebP с fallback на JPEG)
6. Сохранение на диск
7. Создание/обновление записи `MediaVariant`

**Формат пути варианта**:

`{original-directory}/{original-filename}-{variant}.{extension}`

Пример: `2025/01/10/01hxyz123abc-thumbnail.jpg`

**Файл**: `app/Domain/Media/Services/OnDemandVariantService.php`

---

### Job для фоновой генерации

**Класс**: `GenerateVariantJob`

**Использование**:

```php
GenerateVariantJob::dispatch($mediaId, $variant);     // Асинхронно
GenerateVariantJob::dispatchSync($mediaId, $variant); // Синхронно
```

**Поведение**:

-   Загружает медиа-файл (включая удалённые)
-   Если медиа не найдено → job завершается без ошибки

**Файл**: `app/Domain/Media/Jobs/GenerateVariantJob.php`

---

## Хранилище и файловая система

### Конфигурация дисков

Диски настраиваются в `config/filesystems.php`:

```php
'media' => [
    'driver' => env('MEDIA_FILESYSTEM_DRIVER', 'local'),
    'root' => env('MEDIA_LOCAL_ROOT', storage_path('app/public/media')),
    'url' => env('MEDIA_URL', env('APP_URL').'/storage/media'),
    'visibility' => env('MEDIA_VISIBILITY', 'public'),
],
```

**Переменные окружения**:

-   `MEDIA_FILESYSTEM_DRIVER` — драйвер (`local`, `s3`, etc.)
-   `MEDIA_LOCAL_ROOT` — корневая директория для локального диска
-   `MEDIA_URL` — базовый URL для доступа к файлам
-   `MEDIA_VISIBILITY` — видимость файлов (`public`, `private`)

---

### Доставка файлов

#### Локальный диск

-   Файлы отдаются напрямую через `response()->file($path)`
-   `Content-Type` определяется автоматически
-   Статус: `200 OK`

#### Облачный диск (S3)

-   Генерация подписанного URL (`temporaryUrl`)
-   Редирект на URL (`302 Found`, `Location: <signed-url>`)
-   TTL подписанного URL: `config('media.signed_ttl', 300)` секунд (по умолчанию 5 минут)
-   Если `temporaryUrl` недоступен → fallback на обычный `url()`

**Файл**: `app/Http/Controllers/Admin/V1/MediaPreviewController.php@serveFile`

---

## Конфигурация

**Файл**: `config/media.php`

```php
return [
    // Имя диска для хранения медиа
    'disk' => env('MEDIA_DISK', 'media'),

    // Максимальный размер загружаемого файла (MB)
    'max_upload_mb' => env('MEDIA_MAX_UPLOAD_MB', 25),

    // Разрешённые MIME-типы
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'video/mp4',
        'audio/mpeg',
        'application/pdf',
    ],

    // Варианты изображений
    'variants' => [
        'thumbnail' => ['max' => 320],
        'medium' => ['max' => 1024],
    ],

    // TTL подписанных URL (секунды)
    'signed_ttl' => env('MEDIA_SIGNED_TTL', 300),

    // Стратегия организации путей: 'by-date' | 'hash-shard'
    'path_strategy' => env('MEDIA_PATH_STRATEGY', 'by-date'),
];
```

---

## Авторизация и политики доступа

**Политика**: `MediaPolicy`

**Права доступа** (через `User::hasAdminPermission()`):

| Метод           | Право           | Описание                     |
| --------------- | --------------- | ---------------------------- |
| `viewAny()`     | `media.read`    | Просмотр списка медиа        |
| `view()`        | `media.read`    | Просмотр отдельного медиа    |
| `create()`      | `media.create`  | Загрузка нового медиа        |
| `update()`      | `media.update`  | Обновление метаданных        |
| `delete()`      | `media.delete`  | Удаление медиа (soft delete) |
| `restore()`     | `media.restore` | Восстановление удалённого    |
| `forceDelete()` | —               | Всегда `false` (запрещено)   |

**Файл**: `app/Policies/MediaPolicy.php`

---

## Привязка медиа к контенту

Медиа-файлы привязываются к записям контента (Entry) через поля в структуре `data_json`.

Медиа хранятся как ссылки (ULID идентификаторы) в полях структуры контента, а не через отдельную pivot таблицу.

Пример структуры в `data_json`:

```json
{
    "hero_image": "01HXYZ123ABC...",
    "gallery": ["01HXYZ123ABC...", "01HXYZ456DEF...", "01HXYZ789GHI..."]
}
```

---

## API Resources

### MediaResource

Форматирует медиа-файл для ответа API.

**Поля**:

-   `id` — ULID
-   `kind` — тип (`image`, `video`, `audio`, `document`)
-   `name` — оригинальное имя файла
-   `ext`, `mime`, `size_bytes` — метаданные файла
-   `width`, `height`, `duration_ms` — размеры/длительность
-   `title`, `alt`, `collection` — пользовательские данные
-   `created_at`, `updated_at`, `deleted_at` — временные метки
-   `preview_urls` — массив URL для вариантов (только для изображений)
-   `download_url` — URL для скачивания оригинала

**Особенности**:

-   Для только что загруженных медиа устанавливает статус `201 Created`
-   `preview_urls` пустой для не-изображений

**Файл**: `app/Http/Resources/MediaResource.php`

---

### MediaCollection

Форматирует коллекцию медиа-файлов с поддержкой пагинации.

**Структура**:

```json
{
  "data": [...],
  "links": {...},
  "meta": {...}
}
```

**Файл**: `app/Http/Resources/Admin/MediaCollection.php`

---

## Миграции

### `2025_11_06_000040_create_media_table.php`

Создаёт таблицу `media`.

**Индексы**:

-   `checksum_sha256` (для дедупликации)
-   `mime`
-   `collection`
-   `created_at`
-   `deleted_at`

### `2025_11_06_000041_create_media_variants_table.php`

Создаёт таблицу `media_variants`.

**Индексы**:

-   Unique: `[media_id, variant]`

---

## Роуты

**Файл**: `routes/api_admin.php`

**Группа**: `/api/v1/admin/media`

```php
// GET
Route::get('/media', [MediaController::class, 'index']);
Route::get('/media/{media}', [MediaController::class, 'show']);
Route::get('/media/{media}/preview', [MediaPreviewController::class, 'preview']);
Route::get('/media/{media}/download', [MediaPreviewController::class, 'download']);

// POST
Route::post('/media', [MediaController::class, 'store'])
    ->middleware(['can:create,' . Media::class, 'throttle:20,1']);
Route::post('/media/{media}/restore', [MediaController::class, 'restore']);

// PUT
Route::put('/media/{media}', [MediaController::class, 'update']);

// DELETE
Route::delete('/media/{media}', [MediaController::class, 'destroy']);
```

**Middleware**:

-   Аутентификация (через `auth:sanctum` или другой)
-   Авторизация (через `can:` middleware)
-   Rate limiting для загрузки (`throttle:20,1`)

---

## Тесты

**Файл**: `tests/Feature/Admin/Media/MediaApiTest.php`

**Покрытие**:

-   Загрузка медиа
-   Просмотр списка и отдельного медиа
-   Обновление метаданных
-   Удаление и восстановление
-   Предпросмотр и скачивание
-   Авторизация и валидация

---

## Связанные файлы

### Контроллеры

-   `app/Http/Controllers/Admin/V1/MediaController.php`
-   `app/Http/Controllers/Admin/V1/MediaPreviewController.php`

### Модели

-   `app/Models/Media.php`
-   `app/Models/MediaVariant.php`

### Действия и сервисы

-   `app/Domain/Media/Actions/MediaStoreAction.php`
-   `app/Domain/Media/Services/MediaMetadataExtractor.php`
-   `app/Domain/Media/Services/OnDemandVariantService.php`
-   `app/Domain/Media/Jobs/GenerateVariantJob.php`

### Ресурсы

-   `app/Http/Resources/MediaResource.php`
-   `app/Http/Resources/Admin/MediaCollection.php`

### Запросы

-   `app/Http/Requests/Admin/Media/StoreMediaRequest.php`
-   `app/Http/Requests/Admin/Media/UpdateMediaRequest.php`
-   `app/Http/Requests/Admin/Media/IndexMediaRequest.php`

### Политики

-   `app/Policies/MediaPolicy.php`

### Конфигурация

-   `config/media.php`
-   `config/filesystems.php`

### Миграции

-   `database/migrations/2025_11_06_000040_create_media_table.php`
-   `database/migrations/2025_11_06_000041_create_media_variants_table.php`

---

**Дата последнего обновления**: 2025-11-15
