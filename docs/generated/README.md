# Documentation Index

Автоматически сгенерированная документация кодовой базы.

**Всего сущностей:** 231

## Содержание

### [Models](./models.md)
Eloquent-модели для работы с БД (23 сущностей)

### [Domain Services](./domain-services.md)
Доменные сервисы, действия, репозитории (93 сущностей)

### [Blade Views](./blade-views.md)
Blade-шаблоны для рендеринга (8 сущностей)

### [Config Areas](./config-areas.md)
Логические секции конфигурации (24 сущностей)

### [HTTP Endpoints](./http-endpoints.md)
HTTP эндпоинты API (83 сущностей)

## Быстрая навигация

### Models

- [Audit](./models.md#audit) - Eloquent модель для аудита изменений (Audit).
- [Blueprint](./models.md#blueprint) - Шаблон структуры данных для Entry.
- [BlueprintEmbed](./models.md#blueprintembed) - Связь встраивания blueprint'а.
- [DocRef](./models.md#docref) - Индексированная ссылка на другой Entry.
- [DocValue](./models.md#docvalue) - Индексированное скалярное значение из Entry.data_json.
- [Entry](./models.md#entry) - Eloquent модель для записей контента (Entry).
- [FormConfig](./models.md#formconfig) - Eloquent модель для конфигурации формы компонентов (FormConfig).
- [Media](./models.md#media) - Eloquent модель для медиа-файлов (Media).
- [MediaAvMetadata](./models.md#mediaavmetadata) - Eloquent модель для нормализованных AV-метаданных медиа (MediaAvMetadata).
- [MediaImage](./models.md#mediaimage) - Eloquent модель для метаданных изображений (MediaImage).
- *...и еще 13 сущностей*

### Domain Services

- [BladeTemplateResolver](./domain-services.md#bladetemplateresolver) - Резолвер для выбора Blade-шаблона по файловой конвенции.
- [ConditionalRule](./domain-services.md#conditionalrule) - Доменное правило валидации: условное правило.
- [ConditionalRuleHandler](./domain-services.md#conditionalrulehandler) - Обработчик правила ConditionalRule.
- [CorruptionValidator](./domain-services.md#corruptionvalidator) - Валидатор проверки целостности (corruption) медиа-файлов.
- [DistinctRule](./domain-services.md#distinctrule) - Правило валидации: уникальность элементов массива.
- [DistinctRuleHandler](./domain-services.md#distinctrulehandler) - Обработчик правила DistinctRule.
- [ElasticsearchSearchClient](./domain-services.md#elasticsearchsearchclient) - Реализация SearchClientInterface для Elasticsearch.
- [EloquentMediaRepository](./domain-services.md#eloquentmediarepository) - Реализация MediaRepository на базе Eloquent.
- [EntryToSearchDoc](./domain-services.md#entrytosearchdoc) - Трансформер Entry в документ для поискового индекса.
- [EntryValidationService](./domain-services.md#entryvalidationservice) - Доменный сервис валидации контента Entry на основе Blueprint.
- *...и еще 83 сущностей*

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
- [Blueprint](./config-areas.md#blueprint) - Configuration: Blueprint
- [Cache](./config-areas.md#cache) - Configuration: Cache
- [Cors](./config-areas.md#cors) - Configuration: Cors
- [Database](./config-areas.md#database) - Configuration: Database
- [Docs](./config-areas.md#docs) - Configuration: Docs
- [Errors](./config-areas.md#errors) - Configuration: Errors
- [Filesystems](./config-areas.md#filesystems) - Configuration: Filesystems
- [Jwt](./config-areas.md#jwt) - Configuration: Jwt
- *...и еще 14 сущностей*

### HTTP Endpoints

- [admin.v1.auth.current](./http-endpoints.md#admin-v1-auth-current) - GET /api/v1/admin/auth/current (api)
- [admin.v1.blueprints.can-delete](./http-endpoints.md#admin-v1-blueprints-can-delete) - GET /api/v1/admin/blueprints/{blueprint}/can-delete (api)
- [admin.v1.blueprints.dependencies](./http-endpoints.md#admin-v1-blueprints-dependencies) - GET /api/v1/admin/blueprints/{blueprint}/dependencies (api)
- [admin.v1.blueprints.destroy](./http-endpoints.md#admin-v1-blueprints-destroy) - DELETE /api/v1/admin/blueprints/{blueprint} (api)
- [admin.v1.blueprints.embeddable](./http-endpoints.md#admin-v1-blueprints-embeddable) - GET /api/v1/admin/blueprints/{blueprint}/embeddable (api)
- [admin.v1.blueprints.embeds.index](./http-endpoints.md#admin-v1-blueprints-embeds-index) - GET /api/v1/admin/blueprints/{blueprint}/embeds (api)
- [admin.v1.blueprints.embeds.store](./http-endpoints.md#admin-v1-blueprints-embeds-store) - POST /api/v1/admin/blueprints/{blueprint}/embeds (api)
- [admin.v1.blueprints.index](./http-endpoints.md#admin-v1-blueprints-index) - GET /api/v1/admin/blueprints (api)
- [admin.v1.blueprints.paths.index](./http-endpoints.md#admin-v1-blueprints-paths-index) - GET /api/v1/admin/blueprints/{blueprint}/paths (api)
- [admin.v1.blueprints.paths.store](./http-endpoints.md#admin-v1-blueprints-paths-store) - POST /api/v1/admin/blueprints/{blueprint}/paths (api)
- *...и еще 73 сущностей*

## Популярные теги

Документация также индексируется по тегам. См. [index.json](./index.json) для полного индекса.

---

**Сгенерировано:** 2025-12-02 11:40:09

Для обновления документации выполните:
```bash
php artisan docs:generate
```