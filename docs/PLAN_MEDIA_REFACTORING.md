# План переработки системы управления медиа

## Текущее состояние

### Проблемы

1. **Единый ресурс для всех типов медиа** — `MediaResource` возвращает общий набор полей, где:

    - `width`, `height` — только для изображений (null для остальных)
    - `duration_ms` — только для видео/аудио (null для остальных)
    - `preview_urls` — только для изображений (null для остальных)
    - `bitrate_kbps`, `frame_rate`, `video_codec`, `audio_codec` — не возвращаются вообще, хотя есть в `MediaAvMetadata`

2. **Отсутствие типизации** — нет enum для типов медиа, используется строковый метод `kind()`

3. **Неполные данные** — не все метаданные из `MediaAvMetadata` возвращаются в API

### Текущая структура БД

-   `media` — общие поля для всех типов
-   `media_images` — метаданные изображений (width, height, exif_json)
-   `media_av_metadata` — метаданные видео/аудио (duration_ms, bitrate_kbps, frame_rate, frame_count, video_codec, audio_codec)

### Типы медиа (kind)

-   `image` — изображения
-   `video` — видео
-   `audio` — аудио
-   `document` — документы

---

## Целевая архитектура

### Принципы

1. **Разделение по типам** — каждый тип медиа имеет свой ресурс с только релевантными полями
2. **Типобезопасность** — использование enum для типов медиа
3. **Полнота данных** — возврат всех доступных метаданных для каждого типа

### Структура ресурсов

```
MediaResource (базовый/фабрика)
├── MediaImageResource
│   ├── Общие поля (id, kind, name, ext, mime, size_bytes, title, alt, collection, timestamps)
│   ├── width (int, обязательное)
│   ├── height (int, обязательное)
│   ├── preview_urls (array<string, string>, обязательное)
│   └── download_url (string)
│
├── MediaVideoResource
│   ├── Общие поля
│   ├── duration_ms (int|null)
│   ├── bitrate_kbps (int|null)
│   ├── frame_rate (float|null)
│   ├── frame_count (int|null)
│   ├── video_codec (string|null)
│   ├── audio_codec (string|null)
│   └── download_url (string)
│
├── MediaAudioResource
│   ├── Общие поля
│   ├── duration_ms (int|null)
│   ├── bitrate_kbps (int|null)
│   ├── audio_codec (string|null)
│   └── download_url (string)
│
└── MediaDocumentResource
    ├── Общие поля
    └── download_url (string)
```

---

## План реализации

### Этап 1: Создание enum для типов медиа

**Файл:** `app/Domain/Media/MediaKind.php`

```php
enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';

    public static function fromMime(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            str_starts_with($mime, 'video/') => self::Video,
            str_starts_with($mime, 'audio/') => self::Audio,
            default => self::Document,
        };
    }
}
```

**Изменения:**

-   Обновить `Media::kind()` для возврата `MediaKind`
-   Обновить все места, где используется строковый `kind()`

---

### Этап 2: Создание базового абстрактного ресурса

**Файл:** `app/Http/Resources/Media/BaseMediaResource.php`

Базовый класс с общими полями:

-   `id`, `kind`, `name`, `ext`, `mime`, `size_bytes`
-   `title`, `alt`, `collection`
-   `created_at`, `updated_at`, `deleted_at`
-   `download_url`

---

### Этап 3: Создание специализированных ресурсов

#### 3.1 MediaImageResource

**Файл:** `app/Http/Resources/Media/MediaImageResource.php`

**Поля:**

-   Все базовые поля
-   `width` (int, обязательное)
-   `height` (int, обязательное)
-   `preview_urls` (array<string, string>, обязательное)

**Особенности:**

-   Требует загруженную связь `image`
-   `preview_urls` формируется из конфига `media.variants`

#### 3.2 MediaVideoResource

**Файл:** `app/Http/Resources/Media/MediaVideoResource.php`

**Поля:**

-   Все базовые поля
-   `duration_ms` (int|null)
-   `bitrate_kbps` (int|null)
-   `frame_rate` (float|null)
-   `frame_count` (int|null)
-   `video_codec` (string|null)
-   `audio_codec` (string|null)

**Особенности:**

-   Требует загруженную связь `avMetadata`
-   Все AV-поля опциональны (могут быть null)

#### 3.3 MediaAudioResource

**Файл:** `app/Http/Resources/Media/MediaAudioResource.php`

**Поля:**

-   Все базовые поля
-   `duration_ms` (int|null)
-   `bitrate_kbps` (int|null)
-   `audio_codec` (string|null)

**Особенности:**

-   Требует загруженную связь `avMetadata`
-   Не включает видео-специфичные поля (frame_rate, frame_count, video_codec)

#### 3.4 MediaDocumentResource

**Файл:** `app/Http/Resources/Media/MediaDocumentResource.php`

**Поля:**

-   Только базовые поля

---

### Этап 4: Фабричный метод в MediaResource

**Файл:** `app/Http/Resources/MediaResource.php` (переработать)

Превратить в фабрику, которая возвращает нужный ресурс:

```php
public static function make(Media $media): BaseMediaResource
{
    return match ($media->kind()) {
        MediaKind::Image => new MediaImageResource($media),
        MediaKind::Video => new MediaVideoResource($media),
        MediaKind::Audio => new MediaAudioResource($media),
        MediaKind::Document => new MediaDocumentResource($media),
    };
}
```

**Особенности:**

-   `MediaResource` становится фабрикой, которая всегда возвращает специализированный ресурс
-   Прямое использование `new MediaResource($media)` больше не поддерживается

---

### Этап 5: Обновление MediaCollection

**Файл:** `app/Http/Resources/Admin/MediaCollection.php`

**Изменения:**

-   Убрать жесткую привязку к `MediaResource::class`
-   Использовать фабричный метод для каждого элемента

---

### Этап 6: Обновление контроллеров

**Файл:** `app/Http/Controllers/Admin/V1/MediaController.php`

**Изменения:**

1. **index()** — загружать связи в зависимости от типа:

    ```php
    $paginator->getCollection()->loadMissing([
        'image', // для изображений
        'avMetadata', // для видео/аудио
    ]);
    ```

2. **store()**, **show()**, **update()**, **bulkRestore()** — использовать `MediaResource::make()`

3. Обновить PHPDoc с примерами ответов для каждого типа

---

### Этап 7: Обновление модели Media

**Файл:** `app/Models/Media.php`

**Изменения:**

-   Метод `kind()` возвращает `MediaKind` вместо `string`
-   Обновить PHPDoc

---

### Этап 8: Обновление тестов

**Файлы:**

-   `tests/Feature/Api/Media/*.php`
-   `tests/Feature/Media/*.php`
-   `tests/Unit/Models/MediaTest.php`

**Изменения:**

-   Проверять структуру ответов для каждого типа медиа
-   Тестировать, что поля возвращаются только для соответствующих типов
-   Тестировать фабричный метод `MediaResource::make()`

---

### Этап 9: Обновление документации Scribe

**Команда:** `php artisan scribe:gen`

**Изменения:**

-   Обновить примеры ответов в PHPDoc контроллеров
-   Показать разные структуры для разных типов медиа

---

## Детали реализации

### Структура файлов

```
app/Http/Resources/
├── MediaResource.php (фабрика)
└── Media/
    ├── BaseMediaResource.php (абстрактный базовый)
    ├── MediaImageResource.php
    ├── MediaVideoResource.php
    ├── MediaAudioResource.php
    └── MediaDocumentResource.php
```

### Примеры ответов API

#### Изображение

```json
{
    "id": "01HXZYXQJ123456789ABCDEF",
    "kind": "image",
    "name": "hero.jpg",
    "ext": "jpg",
    "mime": "image/jpeg",
    "size_bytes": 235678,
    "width": 1920,
    "height": 1080,
    "title": "Hero image",
    "alt": "Hero cover",
    "collection": "uploads",
    "created_at": "2025-01-10T12:00:00+00:00",
    "updated_at": "2025-01-10T12:00:00+00:00",
    "deleted_at": null,
    "preview_urls": {
        "thumbnail": "https://api.stupidcms.dev/api/v1/admin/media/01HXZYXQJ123456789ABCDEF/preview?variant=thumbnail",
        "medium": "https://api.stupidcms.dev/api/v1/admin/media/01HXZYXQJ123456789ABCDEF/preview?variant=medium",
        "large": "https://api.stupidcms.dev/api/v1/admin/media/01HXZYXQJ123456789ABCDEF/preview?variant=large"
    },
    "download_url": "https://api.stupidcms.dev/api/v1/admin/media/01HXZYXQJ123456789ABCDEF/download"
}
```

#### Видео

```json
{
    "id": "01HXZYXQJ987654321FEDCBA",
    "kind": "video",
    "name": "presentation.mp4",
    "ext": "mp4",
    "mime": "video/mp4",
    "size_bytes": 52428800,
    "duration_ms": 120000,
    "bitrate_kbps": 3500,
    "frame_rate": 30.0,
    "frame_count": 3600,
    "video_codec": "h264",
    "audio_codec": "aac",
    "title": "Presentation video",
    "alt": null,
    "collection": "videos",
    "created_at": "2025-01-10T12:00:00+00:00",
    "updated_at": "2025-01-10T12:00:00+00:00",
    "deleted_at": null,
    "download_url": "https://api.stupidcms.dev/api/v1/admin/media/01HXZYXQJ987654321FEDCBA/download"
}
```

#### Аудио

```json
{
    "id": "01HXZYXQJ111111111111111",
    "kind": "audio",
    "name": "track.mp3",
    "ext": "mp3",
    "mime": "audio/mpeg",
    "size_bytes": 5242880,
    "duration_ms": 180000,
    "bitrate_kbps": 256,
    "audio_codec": "mp3",
    "title": "Audio track",
    "alt": null,
    "collection": "audio",
    "created_at": "2025-01-10T12:00:00+00:00",
    "updated_at": "2025-01-10T12:00:00+00:00",
    "deleted_at": null,
    "download_url": "https://api.stupidcms.dev/api/v1/admin/media/01HXZYXQJ111111111111111/download"
}
```

#### Документ

```json
{
    "id": "01HXZYXQJ222222222222222",
    "kind": "document",
    "name": "document.pdf",
    "ext": "pdf",
    "mime": "application/pdf",
    "size_bytes": 1048576,
    "title": "Document",
    "alt": null,
    "collection": "documents",
    "created_at": "2025-01-10T12:00:00+00:00",
    "updated_at": "2025-01-10T12:00:00+00:00",
    "deleted_at": null,
    "download_url": "https://api.stupidcms.dev/api/v1/admin/media/01HXZYXQJ222222222222222/download"
}
```

---

## Миграции

**Изменения в миграциях не требуются** — структура БД остается прежней.

---

## Тестирование

### Unit-тесты

1. `MediaKind` enum — проверка `fromMime()`
2. `Media::kind()` — возврат `MediaKind`
3. Каждый ресурс — проверка структуры `toArray()`
4. `MediaResource::make()` — выбор правильного ресурса

### Feature-тесты

1. `GET /api/v1/admin/media` — проверка структуры ответов для каждого типа
2. `POST /api/v1/admin/media` — проверка ответа после загрузки
3. `GET /api/v1/admin/media/{id}` — проверка ответа для каждого типа
4. `PUT /api/v1/admin/media/{id}` — проверка обновления

---

## Команды для выполнения

После реализации:

```bash
# Запуск тестов
php artisan test

# Генерация документации Scribe
composer scribe:gen

# Генерация навигационной документации
php artisan docs:generate
```

---

## Breaking changes

1. **Структура ответов API** — поля теперь зависят от типа медиа:

    - Изображения: `width`, `height`, `preview_urls` (обязательные)
    - Видео: `duration_ms`, `bitrate_kbps`, `frame_rate`, `frame_count`, `video_codec`, `audio_codec`
    - Аудио: `duration_ms`, `bitrate_kbps`, `audio_codec`
    - Документы: только базовые поля

2. **Media::kind()** — теперь возвращает `MediaKind` enum вместо `string`

3. **MediaResource** — больше не может использоваться напрямую, только через фабричный метод `MediaResource::make()`

4. **Удаление полей** — поля, не относящиеся к типу медиа, больше не возвращаются (например, `width`/`height` для видео)

---

## Преимущества новой архитектуры

1. **Типобезопасность** — enum вместо строк
2. **Чистота данных** — только релевантные поля для каждого типа
3. **Расширяемость** — легко добавить новые типы медиа
4. **Полнота** — все метаданные из БД возвращаются в API
5. **Читаемость** — явная структура для каждого типа

---

## Порядок выполнения

1. ✅ Создать enum `MediaKind`
2. ✅ Обновить `Media::kind()` для возврата enum
3. ✅ Создать `BaseMediaResource`
4. ✅ Создать специализированные ресурсы
5. ✅ Переработать `MediaResource` как фабрику
6. ✅ Обновить `MediaCollection`
7. ✅ Обновить контроллеры
8. ✅ Обновить тесты
9. ✅ Обновить документацию

---

**Дата создания:** 2025-01-18
**Статус:** План готов к реализации
