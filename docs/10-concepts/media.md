---
owner: "@backend-team"
system_of_record: "narrative"
review_cycle_days: 60
last_reviewed: 2025-11-08
related_code:
  - "app/Models/Media.php"
  - "app/Models/MediaVariant.php"
  - "app/Http/Controllers/Admin/MediaController.php"
  - "config/filesystems.php"
---

# Media (медиатека)

**Media** — система управления файлами (изображения, документы, видео) в stupidCms.

## Концепция

### Ключевые возможности

- **Централизованная библиотека** — все файлы в одном месте
- **Варианты изображений** — автоматическое создание thumbnails, medium, large
- **Метаданные** — EXIF, alt, title, dimensions
- **Переиспользование** — один файл можно привязать к нескольким entries
- **Права доступа** — контроль через MediaPolicy
- **Хранилища** — local, S3, MinIO и другие (через Laravel Storage)

## Модель данных

### Media

**Таблица**: `media`

```php
Media {
  id: bigint (PK)
  uploader_id: bigint (FK → users.id)
  filename: string              // 'photo.jpg'
  path: string                  // 'media/2025/11/08/abc123.jpg'
  mime_type: string             // 'image/jpeg'
  size_bytes: bigint            // 1048576
  meta_json: json               // EXIF, alt, title, dimensions
  created_at: datetime
  updated_at: datetime
  deleted_at: ?datetime         // soft delete
}
```

**Связи**:
- `belongsTo(User, 'uploader_id')` — кто загрузил
- `hasMany(MediaVariant)` — варианты (thumbnails)
- `belongsToMany(Entry)` via `entry_media` — к каким entries привязан

**Файл**: `app/Models/Media.php`

---

### MediaVariant

**Назначение**: Варианты изображения (обработанные версии).

**Таблица**: `media_variants`

```php
MediaVariant {
  id: bigint (PK)
  media_id: bigint (FK → media.id)
  variant: string               // 'thumbnail', 'medium', 'large'
  path: string                  // 'media/2025/11/08/abc123-thumb.jpg'
  width: int
  height: int
  size_bytes: bigint
  created_at: datetime
  updated_at: datetime
}
```

**Связи**:
- `belongsTo(Media)`

**Файл**: `app/Models/MediaVariant.php`

---

### EntryMedia (Pivot)

**Назначение**: Связь Entry ↔ Media с метаданными.

**Таблица**: `entry_media`

```php
EntryMedia {
  entry_id: bigint (FK → entries.id, часть PK)
  media_id: bigint (FK → media.id, часть PK)
  field_key: string             // 'featured_image', 'gallery', 'attachment'
  order: int                    // порядок в галерее
}
```

**Primary Key**: composite `(entry_id, media_id, field_key)`

**Файл**: `app/Models/EntryMedia.php`

## Жизненный цикл медиафайла

### 1. Загрузка

```php
POST /api/admin/media
Content-Type: multipart/form-data

file: <binary>
alt: "Описание изображения"
title: "Заголовок"
```

**Что происходит**:

1. **Валидация**:
   ```php
   $request->validate([
       'file' => 'required|file|max:10240|mimes:jpg,png,webp,pdf',
   ]);
   ```

2. **Сохранение файла**:
   ```php
   $path = $request->file('file')->store('media/' . date('Y/m/d'), 'public');
   ```

3. **Извлечение метаданных**:
   ```php
   $mime = $file->getMimeType();
   $size = $file->getSize();
   $meta = $this->extractMeta($file); // EXIF, dimensions
   ```

4. **Создание записи Media**:
   ```php
   $media = Media::create([
       'uploader_id' => auth()->id(),
       'filename' => $file->getClientOriginalName(),
       'path' => $path,
       'mime_type' => $mime,
       'size_bytes' => $size,
       'meta_json' => $meta + ['alt' => $request->alt, 'title' => $request->title],
   ]);
   ```

5. **Генерация вариантов** (если изображение):
   ```php
   $this->generateVariants($media);
   ```

---

### 2. Генерация вариантов

Для изображений создаются уменьшенные версии:

```php
// config/media.php (пример)

'variants' => [
    'thumbnail' => ['width' => 150, 'height' => 150],
    'medium' => ['width' => 600, 'height' => 600],
    'large' => ['width' => 1200, 'height' => 1200],
],
```

**Job/Service**:

```php
use Intervention\Image\Facades\Image;

foreach (config('media.variants') as $variant => $dimensions) {
    $img = Image::make($media->fullPath())
        ->fit($dimensions['width'], $dimensions['height']);
    
    $variantPath = str_replace('.jpg', "-{$variant}.jpg", $media->path);
    $img->save(storage_path('app/public/' . $variantPath));
    
    MediaVariant::create([
        'media_id' => $media->id,
        'variant' => $variant,
        'path' => $variantPath,
        'width' => $img->width(),
        'height' => $img->height(),
        'size_bytes' => filesize(storage_path('app/public/' . $variantPath)),
    ]);
}
```

> 📦 **Пакеты**: `intervention/image` для обработки изображений.

---

### 3. Привязка к Entry

```php
$entry->media()->attach($mediaId, [
    'field_key' => 'featured_image',
    'order' => 0,
]);
```

**Результат** в `entry_media`:
```sql
entry_id | media_id | field_key       | order
---------+----------+-----------------+------
1        | 10       | featured_image  | 0
```

---

### 4. Получение URL

```php
$media = Media::find(10);

// Оригинал
$url = Storage::url($media->path);
// /storage/media/2025/11/08/abc123.jpg

// Thumbnail
$thumbnail = $media->variants()->where('variant', 'thumbnail')->first();
$thumbUrl = Storage::url($thumbnail->path);
// /storage/media/2025/11/08/abc123-thumbnail.jpg
```

---

### 5. Удаление

```php
$media->delete(); // soft delete
```

**Что происходит**:
- `deleted_at` устанавливается
- Файлы НЕ удаляются (для возможного восстановления)

**Force delete**:
```php
$media->forceDelete();
```

**Что происходит**:
- Удаляется запись из БД
- Удаляются файлы:
  ```php
  Storage::delete($media->path);
  foreach ($media->variants as $variant) {
      Storage::delete($variant->path);
  }
  ```

## meta_json структура

### Для изображений

```json
{
  "alt": "Описание для accessibility",
  "title": "Заголовок изображения",
  "dimensions": {
    "width": 1920,
    "height": 1080
  },
  "exif": {
    "camera": "Canon EOS R5",
    "iso": 100,
    "aperture": "f/2.8",
    "taken_at": "2025-11-08T12:00:00Z"
  }
}
```

### Для документов

```json
{
  "title": "Отчёт Q4 2025",
  "pages": 25,
  "author": "John Doe"
}
```

## Привязка к Entry

### Featured Image (одно изображение)

```php
// Установить featured image
$entry->media()->syncWithoutDetaching([
    $mediaId => ['field_key' => 'featured_image', 'order' => 0]
]);

// Получить featured image
$featuredMedia = $entry->media()
    ->wherePivot('field_key', 'featured_image')
    ->first();
```

---

### Gallery (несколько изображений)

```php
// Установить галерею (с порядком)
$entry->media()->syncWithoutDetaching([
    10 => ['field_key' => 'gallery', 'order' => 1],
    11 => ['field_key' => 'gallery', 'order' => 2],
    12 => ['field_key' => 'gallery', 'order' => 3],
]);

// Получить галерею
$gallery = $entry->media()
    ->wherePivot('field_key', 'gallery')
    ->orderByPivot('order')
    ->get();
```

---

### Кастомные поля

```php
// Прикрепить файл к кастомному полю
$entry->media()->attach($pdfId, [
    'field_key' => 'attachment_report',
    'order' => 0,
]);
```

## API

### Загрузка медиа

**Endpoint**: `POST /api/admin/media`

**Request** (multipart/form-data):
```
file: <binary>
alt: "Описание"
title: "Заголовок"
```

**Response**: `201 Created`
```json
{
  "data": {
    "id": 10,
    "filename": "photo.jpg",
    "url": "/storage/media/2025/11/08/abc123.jpg",
    "mime_type": "image/jpeg",
    "size_bytes": 1048576,
    "meta_json": {
      "alt": "Описание",
      "dimensions": {"width": 1920, "height": 1080}
    },
    "variants": [
      {
        "variant": "thumbnail",
        "url": "/storage/media/2025/11/08/abc123-thumbnail.jpg",
        "width": 150,
        "height": 150
      }
    ],
    "created_at": "2025-11-08T12:00:00Z"
  }
}
```

---

### Получение списка медиа

**Endpoint**: `GET /api/admin/media`

**Query**:
- `?mime_type=image/*` — фильтр по типу
- `?uploader_id=5` — фильтр по загрузчику
- `?page=2` — пагинация

**Response**:
```json
{
  "data": [
    {
      "id": 10,
      "filename": "photo.jpg",
      "url": "/storage/...",
      "thumbnail_url": "/storage/...-thumbnail.jpg"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

---

### Обновление метаданных

**Endpoint**: `PUT /api/admin/media/{id}`

**Request**:
```json
{
  "meta_json": {
    "alt": "Новое описание",
    "title": "Новый заголовок"
  }
}
```

---

### Удаление

**Endpoint**: `DELETE /api/admin/media/{id}`

**Response**: `204 No Content`

> ⚠️ **Проверка**: Нельзя удалить медиа, если оно привязано к entries (или сделать soft delete).

## Хранилища

### Local (разработка)

```env
# .env
FILESYSTEM_DISK=public
```

Файлы в `storage/app/public/media/*`

---

### S3 (production)

```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=stupidcms-media
AWS_URL=https://cdn.example.com
```

**config/filesystems.php**:
```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'visibility' => 'public',
],
```

---

### MinIO (self-hosted S3)

```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=stupidcms
AWS_ENDPOINT=http://localhost:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

## Права доступа (MediaPolicy)

**Файл**: `app/Policies/MediaPolicy.php`

```php
public function view(User $user, Media $media): bool
{
    return true; // все могут просматривать
}

public function update(User $user, Media $media): bool
{
    return $user->id === $media->uploader_id || $user->role === 'admin';
}

public function delete(User $user, Media $media): bool
{
    // Проверка: не привязан ли к entries
    if ($media->entries()->exists()) {
        return false;
    }
    
    return $user->id === $media->uploader_id || $user->role === 'admin';
}
```

## Best Practices

### ✅ DO

- Генерируйте варианты асинхронно (через Queue)
- Используйте CDN для production (CloudFlare, CloudFront)
- Оптимизируйте изображения при загрузке (WebP, сжатие)
- Храните оригиналы в S3/MinIO
- Используйте alt для accessibility

### ❌ DON'T

- Не храните медиа в БД (BLOB) — только метаданные
- Не генерируйте варианты синхронно (долго)
- Не удаляйте медиа force, если не уверены
- Не разрешайте неограниченный размер файлов

## Производительность

### Lazy Loading вариантов

```php
Media::with('variants')->get();
```

Вместо N+1 запросов.

### Кэширование URL

```php
$media->url = Cache::remember("media:{$media->id}:url", 3600, fn() => 
    Storage::url($media->path)
);
```

### CDN

Используйте `AWS_URL` для отдачи через CDN:

```env
AWS_URL=https://d111111abcdef8.cloudfront.net
```

URL будет: `https://d111111abcdef8.cloudfront.net/media/2025/11/08/abc123.jpg`

## Pipeline (автоматизация)

### События

```php
// app/Events/MediaUploaded.php

class MediaUploaded
{
    public Media $media;
}
```

### Listeners

```php
// app/Listeners/GenerateMediaVariants.php

public function handle(MediaUploaded $event): void
{
    if (str_starts_with($event->media->mime_type, 'image/')) {
        GenerateVariantsJob::dispatch($event->media);
    }
}
```

```php
// app/Listeners/OptimizeImage.php

public function handle(MediaUploaded $event): void
{
    // Оптимизация через TinyPNG, ImageOptim и т.д.
}
```

Подробнее: [Media Pipeline Reference](../30-reference/media-pipeline.md)

## Связанные страницы

- [Entries](entries.md) — привязка медиа к записям
- [Media Pipeline](../30-reference/media-pipeline.md) — автоматизация обработки
- [How-to: Загрузка медиа](../20-how-to/media-upload.md)
- [Config Reference](../30-reference/config.md) — настройки хранилища

---

> 💡 **Tip**: Используйте Laravel Horizon для мониторинга очереди обработки медиа.

