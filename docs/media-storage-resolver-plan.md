# План реализации CDN/Storage абстракции (StorageResolver)

Этот документ конкретизирует пункт roadmap:

> Добавить CDN/storage абстракцию: расширить config до нескольких дисков по коллекциям, внедрив StorageResolver (например, S3 для видео, локальный для документов).

## 1. Текущее состояние

- Все загрузки медиа идут на один логический диск:
  - `config/media.php` → ключ `disk` указывает диск `media`.
  - `config/filesystems.php` → диск `media` (driver `local`/`s3` и т.п.).
- `MediaStoreAction::execute()`:
  - Берёт `$diskName = config('media.disk', 'media');`
  - `$disk = Storage::disk($diskName);`
  - Сохраняет файл и записывает `Media::disk = $diskName`.
- Чтение/отдача файлов уже пер-дисковые:
  - `OnDemandVariantService` использует `Storage::disk($media->disk)`.
  - `MediaPreviewController::serveFile()` использует `$diskName` из `Media::disk` и сам решает local vs cloud через `path()` / `temporaryUrl()` / `url()`.

**Важно:** модель `Media` уже умеет хранить разные диски, вся привязка к одному диску — только в конфиге и в `MediaStoreAction`.

## 2. Цель

- Ввести слой абстракции для выбора диска при загрузке:
  - По `collection` (и, при необходимости, по типу/`mime`) выбирать логический диск (`media`, `media_videos`, `media_documents`, ...).
  - Сохранить выбранный диск в `Media::disk`, чтобы downstream‑код (preview/варианты/скачивание) продолжил работать без изменений.
- Заложить основу для дальнейшего расширения до полноценного CDN/URL‑резолвера (отдельный host, TTL и т.п.), но на первом шаге — только выбор диска.

## 3. Дизайн на уровне конфигурации

### 3.1. Расширение `config/media.php`

1. Добавить новый раздел `disks`:
   - `disks.default` — логический диск по умолчанию для всех медиа.
   - `disks.collections` — мапа коллекций на диски:
     - Примеры:  
       - `videos` → `media_videos` (S3/CloudFront)  
       - `documents` → `media_documents` (локальное/приватное)  
       - `uploads` → `media` (дефолт)
   - (Опционально) `disks.kinds` — мапа типов медиа на диски:
     - `image`, `video`, `audio`, `document` → имена дисков.

2. Обеспечить дефолтное поведение:
   - Если `media.disks.default` не задан, fallback → `'media'`.

### 3.2. Настройка дисков в `config/filesystems.php`

1. Проверить существование диска `media` (уже есть).
2. При необходимости добавить новые диски, согласованные с `disks.collections`:
   - `media_videos` — driver `s3`, отдельный bucket/endpoint/CDN для видео.
   - `media_documents` — driver `local` или отдельный `s3` bucket для документов.
3. Следить, чтобы имена дисков в `config/media.php` совпадали с именами дисков в `config/filesystems.php`.

## 4. Сервис `StorageResolver`

### 4.1. Размещение и назначение

- Файл: `app/Domain/Media/Services/StorageResolver.php`.
- Задача: инкапсулировать всю логику выбора диска по:
  - `collection` (основной критерий),
  - inferred `kind` по MIME (резервный критерий),
  - дефолтному диску (`disks.default` / `disk`).

### 4.2. Публичный интерфейс

- `resolveDiskName(?string $collection, ?string $mime = null): string`
  - Вход:
    - `collection` — значение из payload (`StoreMediaRequest` → `MediaStoreAction`).
    - `mime` — определённый MIME файла до сохранения.
  - Выход:
    - Имя логического диска (ключ в `filesystems.disks`).

- (Опционально, для удобства)  
  `filesystemForUpload(?string $collection, ?string $mime = null): \Illuminate\Contracts\Filesystem\Filesystem`
  - Использует `resolveDiskName()` и возвращает `Storage::disk($diskName)`.
  - Можно не использовать наружу, а держать как internal helper.

### 4.3. Логика резолвинга диска

1. Нормализация входных данных:
   - `collection`:
     - `null` оставлять `null`,
     - непустые строки — `trim`, при необходимости `strtolower()` (если коллекции считаются case-insensitive; нужно синхронизировать с текущей логикой валидации).
   - `mime`:
     - использовать как есть, если это строка.

2. Попытка резолва по коллекции:
   - Если `collection` не `null` и существует ключ `media.disks.collections[collection]`:
     - вернуть соответствующий диск.

3. Попытка резолва по inferred kind:
   - Определить `kind` из `mime` (по аналогии с `Media::kind()`):  
     - `image/` → `image`  
     - `video/` → `video`  
     - `audio/` → `audio`  
     - иначе → `document`.
   - Если `media.disks.kinds[kind]` существует, вернуть этот диск.

4. Дефолтный диск:
   - Если `media.disks.default` задан — вернуть его.
   - Иначе вернуть `'media'` (жёсткий fallback).

5. Ошибочные конфиги:
   - Не бросать исключения из-за отсутствия ключей в конфиге — всегда иметь fallback.
   - Ошибки типа "такого диска нет в `config/filesystems.php`" пусть всплывают на стадии обращения к `Storage::disk()` (как сейчас), это не задача резолвера.

### 4.4. Регистрация в контейнере

- В подходящем `ServiceProvider` (например, где уже регистрируются медиасервисы):
  - Зарегистрировать `StorageResolver` как `singleton` (без параметров — он использует только `config()`).
- При желании добавить интерфейс `StorageResolverInterface` для дальнейшей подмены в тестах/адаптации, но на первом шаге можно обойтись конкретным классом.

## 5. Интеграция в `MediaStoreAction`

### 5.1. Обновление конструктора

- Сейчас конструктор принимает только `MediaMetadataExtractor`.
- Добавить зависимость:
  - `StorageResolver $storageResolver` (readonly‑свойство).
- Обновить PHPDoc для конструктора:
  - Описать оба параметра (`MediaMetadataExtractor`, `StorageResolver`).

### 5.2. Использование в `execute()`

1. До выбора диска:
   - Определить `$mime` (как сейчас).
   - Вытянуть `$collection = $payload['collection'] ?? null;`.

2. Вместо прямого:
   - `\$diskName = config('media.disk', 'media');`
   - `\$disk = Storage::disk($diskName);`

   использовать:

   - `\$diskName = $this->storageResolver->resolveDiskName($collection, $mime);`
   - `\$disk = Storage::disk($diskName);`  
     (или `filesystemForUpload()`, если он реализован).

3. При создании `Media`:
   - Поле `disk` оставить:
     - `'disk' => $diskName,` — теперь это результат работы резолвера.

### 5.3. Дедупликация по checksum

- Текущее поведение:
  - Дедупликация производится по `checksum_sha256` **без учёта диска/коллекции**.
  - При найденном дубликате возвращается существующая запись, новый файл не сохраняется.
- Нюансы в контексте нескольких дисков:
  - Один и тот же файл, загруженный в разные коллекции → будет считаться дубликатом и привязан к **первому сохранённому** диску.
  - Если позже поменять конфиг маршрутизации дисков, старые файлы останутся на прежних дисках — это уже ожидаемое поведение.
- На первом шаге:
  - Сохранить текущее поведение дедупликации (глобальной по checksum).
  - Вынести в документацию, что дедуп — глобальный, а не per‑collection/per‑disk.
  - При необходимости изменить стратегию дедупликации — сделать отдельной задачей.

## 6. Влияние на остальной код

### 6.1. OnDemandVariantService

- Использует `Storage::disk($media->disk)` при чтении/записи вариантов.
- После внедрения `StorageResolver` диск по-прежнему берётся из `Media::disk`, поэтому:
  - Изменений в этом сервисе не требуется.
  - Варианты будут храниться на том же диске, что и оригинал.

### 6.2. MediaPreviewController

- Методы `preview()` / `download()` вызывают `serveFile($media->disk, $path)`.
- `serveFile()` сам различает локальный и облачный диски:
  - Пытается использовать `$disk->path()` → `response()->file()`.
  - В случае исключения — `temporaryUrl()` или `url()` и редирект.
- Так как поле `Media::disk` теперь определяется `StorageResolver`, никаких изменений в контроллере не требуется.

## 7. Тестирование

### 7.1. Unit‑тесты для `StorageResolver`

- Файл: `tests/Unit/Domain/Media/StorageResolverTest.php`.
- Основные кейсы:
  1. **Default fallback**:
     - Конфиг: задан только `media.disk = 'media'` (или `disks.default`).
     - Вызов без `collection` и `mime` → возвращается дефолтный диск.
  2. **Резолв по коллекции**:
     - `config(['media.disks.collections' => ['videos' => 'media_videos']]);`
     - Вызов с `collection = 'videos'` → диск `media_videos`.
  3. **Резолв по kind (mime)**:
     - `config(['media.disks.kinds' => ['video' => 'media_videos']]);`
     - Вызов с `mime = 'video/mp4'`, без коллекции → диск `media_videos`.
  4. **Неизвестная коллекция и kind**:
     - Нет подходящих ключей в массивах → возвращается дефолт.
- В тестах использовать `config()->set()` для временного изменения настроек.

### 7.2. Feature‑тесты загрузки медиа

- Расширить существующие тесты или добавить отдельный, например `tests/Feature/Admin/Media/MediaStorageResolverTest.php`.

Кейсы:

1. **Upload без коллекции → дефолтный диск**:
   - `Storage::fake('media');`
   - `config(['media.disks.default' => 'media']);`
   - Отправить `POST /api/v1/admin/media` без `collection`.
   - Проверить:
     - Файл записан на `media` (`Storage::disk('media')->allFiles()` / `assertExists()`).
     - В БД у `Media` поле `disk` = `media`.

2. **Upload с коллекцией `videos` → кастомный диск**:
   - `Storage::fake('media_videos');`
   - `config(['media.disks.collections' => ['videos' => 'media_videos']]);`
   - Отправить `POST /api/v1/admin/media` с `collection = 'videos'`.
   - Проверить:
     - Файл на диске `media_videos`.
     - В БД `Media::disk = 'media_videos'`.

3. **Upload с неизвестной коллекцией → дефолтный диск**:
   - `Storage::fake('media');`
   - `config(['media.disks.default' => 'media', 'media.disks.collections' => ['videos' => 'media_videos']]);`
   - Отправить запрос с `collection = 'other'`.
   - Ожидаемый результат:
     - Файл на диске `media`.
     - `Media::disk = 'media'`.

4. **Совместимость существующих тестов**:
   - Большинство текущих тестов используют `Storage::fake('media');` и не передают `collection`.
   - При `disks.default = 'media'` поведение не меняется.
   - Для новых коллекций/дисков в тестах всегда добавлять `Storage::fake('<disk_name>');`.

## 8. Обновление документации

1. Обновить `docs/media-system.md` в секции «Хранилище и файловая система»:
   - Описать новую конфигурацию `media.disks`.
   - Пояснить, что:
     - `Media::disk` хранит логическое имя диска.
     - Выбор диска при загрузке осуществляет `StorageResolver`.
     - Все downstream‑операции (preview/variants/download) опираются на `Media::disk`, поэтому прозрачно работают и для локальных, и для облачных дисков.
2. Добавить замечание о глобальной дедупликации по checksum:
   - Дедуп независим от диска/коллекции, это ожидаемое поведение.
   - Изменение стратегии дедупликации — отдельная задача.

## 9. Команды после внедрения

После реализации изменений нужно выполнить:

```bash
php artisan test
composer scribe:gen
php artisan docs:generate
```


