# Documentation Index

Автоматически сгенерированная документация кодовой базы.

**Всего сущностей:** 148

## Содержание

### [Models](./models.md)
Eloquent-модели для работы с БД (18 сущностей)

### [Domain Services](./domain-services.md)
Доменные сервисы, действия, репозитории (40 сущностей)

### [Blade Views](./blade-views.md)
Blade-шаблоны для рендеринга (8 сущностей)

### [Config Areas](./config-areas.md)
Логические секции конфигурации (23 сущностей)

### [HTTP Endpoints](./http-endpoints.md)
HTTP эндпоинты API (59 сущностей)

## Быстрая навигация

### Models

- [Audit](./models.md#audit) - Eloquent модель для аудита изменений (Audit).
- [Entry](./models.md#entry) - Eloquent модель для записей контента (Entry).
- [EntryMedia](./models.md#entrymedia) - Pivot модель для связи записей и медиа-файлов (EntryMedia).
- [EntrySlug](./models.md#entryslug) - Eloquent модель для истории slug'ов записей (EntrySlug).
- [Media](./models.md#media) - Eloquent модель для медиа-файлов (Media).
- [MediaVariant](./models.md#mediavariant) - Eloquent модель для вариантов медиа-файлов (MediaVariant).
- [Option](./models.md#option) - Eloquent модель для опций системы (Option).
- [Outbox](./models.md#outbox) - Eloquent модель для исходящих сообщений (Outbox).
- [Plugin](./models.md#plugin) - Eloquent модель для плагинов (Plugin).
- [PostType](./models.md#posttype) - Eloquent модель для типов записей (PostType).
- *...и еще 8 сущностей*

### Domain Services

- [BladeTemplateResolver](./domain-services.md#bladetemplateresolver) - Резолвер для выбора Blade-шаблона по файловой конвенции.
- [DefaultEntrySlugService](./domain-services.md#defaultentryslugservice) - Сервис для управления историей slug'ов записей.
- [ElasticsearchSearchClient](./domain-services.md#elasticsearchsearchclient) - Реализация SearchClientInterface для Elasticsearch.
- [EntryToSearchDoc](./domain-services.md#entrytosearchdoc) - Трансформер Entry в документ для поискового индекса.
- [GenerateVariantJob](./domain-services.md#generatevariantjob) - Job для генерации варианта медиа-файла.
- [IndexManager](./domain-services.md#indexmanager) - Менеджер для управления индексами поиска.
- [JwtService](./domain-services.md#jwtservice) - Service for issuing and verifying JWT access and refresh tokens.
- [MediaMetadataExtractor](./domain-services.md#mediametadataextractor) - Сервис для извлечения метаданных из медиа-файлов.
- [MediaStoreAction](./domain-services.md#mediastoreaction) - Действие для сохранения медиа-файла.
- [NotReservedRoute](./domain-services.md#notreservedroute) - Правило валидации: slug не должен быть зарезервированным путём.
- *...и еще 30 сущностей*

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
- *...и еще 49 сущностей*

## Популярные теги

Документация также индексируется по тегам. См. [index.json](./index.json) для полного индекса.

---

**Сгенерировано:** 2025-11-14 15:16:35

Для обновления документации выполните:
```bash
php artisan docs:generate
```