# Documentation Index

Автоматически сгенерированная документация кодовой базы.

**Всего сущностей:** 179

## Содержание

### [Models](./models.md)
Eloquent-модели для работы с БД (22 сущностей)

### [Domain Services](./domain-services.md)
Доменные сервисы, действия, репозитории (65 сущностей)

### [Blade Views](./blade-views.md)
Blade-шаблоны для рендеринга (8 сущностей)

### [Config Areas](./config-areas.md)
Логические секции конфигурации (23 сущностей)

### [HTTP Endpoints](./http-endpoints.md)
HTTP эндпоинты API (61 сущностей)

## Быстрая навигация

### Models

- [Audit](./models.md#audit) - Eloquent модель для аудита изменений (Audit).
- [Blueprint](./models.md#blueprint) - Шаблон структуры данных для Entry.
- [BlueprintEmbed](./models.md#blueprintembed) - Связь встраивания blueprint'а.
- [DocRef](./models.md#docref) - Индексированная ссылка на другой Entry.
- [DocValue](./models.md#docvalue) - Индексированное скалярное значение из Entry.data_json.
- [Entry](./models.md#entry) - Eloquent модель для записей контента (Entry).
- [Media](./models.md#media) - Eloquent модель для медиа-файлов (Media).
- [MediaAvMetadata](./models.md#mediaavmetadata) - Eloquent модель для нормализованных AV-метаданных медиа (MediaAvMetadata).
- [MediaImage](./models.md#mediaimage) - Eloquent модель для метаданных изображений (MediaImage).
- [MediaVariant](./models.md#mediavariant) - Eloquent модель для вариантов медиа-файлов (MediaVariant).
- *...и еще 12 сущностей*

### Domain Services

- [BladeTemplateResolver](./domain-services.md#bladetemplateresolver) - Резолвер для выбора Blade-шаблона по файловой конвенции.
- [CorruptionValidator](./domain-services.md#corruptionvalidator) - Валидатор проверки целостности (corruption) медиа-файлов.
- [ElasticsearchSearchClient](./domain-services.md#elasticsearchsearchclient) - Реализация SearchClientInterface для Elasticsearch.
- [EloquentMediaRepository](./domain-services.md#eloquentmediarepository) - Реализация MediaRepository на базе Eloquent.
- [EntryToSearchDoc](./domain-services.md#entrytosearchdoc) - Трансформер Entry в документ для поискового индекса.
- [ExifManager](./domain-services.md#exifmanager) - Менеджер для управления EXIF данными изображений.
- [ExiftoolMediaMetadataPlugin](./domain-services.md#exiftoolmediametadataplugin) - Плагин метаданных, основанный на утилите exiftool.
- [FfprobeMediaMetadataPlugin](./domain-services.md#ffprobemediametadataplugin) - Плагин метаданных, основанный на утилите ffprobe.
- [GdImageProcessor](./domain-services.md#gdimageprocessor) - Реализация ImageProcessor на базе GD.
- [GenerateVariantJob](./domain-services.md#generatevariantjob) - Job для генерации варианта медиа-файла.
- *...и еще 55 сущностей*

### Blade Views

- [404](./blade-views.md#404) - Page template: resources/views/errors/404.blade.php
- [app](./blade-views.md#app) - Page template: resources/views/layouts/app.blade.php
- [default](./blade-views.md#default) - Page template: resources/views/home/default.blade.php
- [entry](./blade-views.md#entry) - Page template: resources/views/entry.blade.php
- [footer](./blade-views.md#footer) - Page template: resources/views/partials/footer.blade.php
- [header](./blade-views.md#header) - Page template: resources/views/partials/header.blade.php
- [public](./blade-views.md#public) - Page template: resources/views/layouts/public.blade.php
- [show](./blade-views.md#show) - Page template: resources/views/pages/show.blade.php

### Config Areas

- [App](./config-areas.md#app) - Configuration: App
- [Auth](./config-areas.md#auth) - Configuration: Auth
- [Cache](./config-areas.md#cache) - Configuration: Cache
- [Cors](./config-areas.md#cors) - Configuration: Cors
- [Database](./config-areas.md#database) - Configuration: Database
- [Docs](./config-areas.md#docs) - Configuration: Docs
- [Errors](./config-areas.md#errors) - Configuration: Errors
- [Filesystems](./config-areas.md#filesystems) - Configuration: Filesystems
- [Jwt](./config-areas.md#jwt) - Configuration: Jwt
- [Logging](./config-areas.md#logging) - Configuration: Logging
- *...и еще 13 сущностей*

### HTTP Endpoints

- [admin.v1.auth.current](./http-endpoints.md#admin-v1-auth-current) - GET /api/v1/admin/auth/current (api)
- [admin.v1.entries.destroy](./http-endpoints.md#admin-v1-entries-destroy) - DELETE /api/v1/admin/entries/{id} (api)
- [admin.v1.entries.index](./http-endpoints.md#admin-v1-entries-index) - GET /api/v1/admin/entries (api)
- [admin.v1.entries.restore](./http-endpoints.md#admin-v1-entries-restore) - POST /api/v1/admin/entries/{id}/restore (api)
- [admin.v1.entries.show](./http-endpoints.md#admin-v1-entries-show) - GET /api/v1/admin/entries/{id} (api)
- [admin.v1.entries.statuses](./http-endpoints.md#admin-v1-entries-statuses) - GET /api/v1/admin/entries/statuses (api)
- [admin.v1.entries.store](./http-endpoints.md#admin-v1-entries-store) - POST /api/v1/admin/entries (api)
- [admin.v1.entries.terms.index](./http-endpoints.md#admin-v1-entries-terms-index) - GET /api/v1/admin/entries/{entry}/terms (api)
- [admin.v1.entries.terms.sync](./http-endpoints.md#admin-v1-entries-terms-sync) - PUT /api/v1/admin/entries/{entry}/terms/sync (api)
- [admin.v1.entries.update](./http-endpoints.md#admin-v1-entries-update) - PUT /api/v1/admin/entries/{id} (api)
- *...и еще 51 сущностей*

## Популярные теги

Документация также индексируется по тегам. См. [index.json](./index.json) для полного индекса.

---

**Сгенерировано:** 2025-11-20 12:17:05

Для обновления документации выполните:
```bash
php artisan docs:generate
```