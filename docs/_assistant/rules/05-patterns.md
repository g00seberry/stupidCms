# Patterns

## Error Handling

-   Возвращай RFC7807: type, title, status, detail, code, instance.
-   Для 422 валидации — вложи поля в `errors`.

## Slug Resolution

-   Источник: `app/Support/Slug/*`, `app/Models/EntrySlug.php`
-   При смене slug — записывай историю; 301 на старые.
-   Проверяй reserved routes перед сохранением.

## Entry Publishing

-   Поля: `status` (draft/published), `published_at`.
-   Сервис публикации: `app/Domain/Entries/PublishingService.php`.
-   Невидимые записи (draft или deleted) исключаются из публичных выборок.

## Reserved Routes

-   Реестр: `app/Support/ReservedRoutes/*`.
-   Валидация на create/update slug.

> Для каждого паттерна — приводи 1 короткий пример запроса/ответа или вызова сервиса.
