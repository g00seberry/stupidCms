---
owner: "@backend-team"
status: "draft"
---

# API Docs (Scribe) Playbook

## Scope

-   Все HTTP endpoints (`routes/api.php`, `routes/api_admin.php`, `routes/web_core.php` с API).
-   Исключения фиксируются в `config/scribe.php` (`routes.exclude`).
-   Документируем контроллеры, FormRequest, Resources, проблемы (`Traits\Problems`).

## Общие требования

-   `declare(strict_types=1);` в примерах.
-   PHPDoc/атрибуты Scribe внутри контроллеров; при конфликте с кодом — обновляем код и docs.
-   Группы (`@group` или `#[Group]`) совпадают с доменными областями (`Auth`, `Entries`, `Media`, `Options`, `Plugins`, `Taxonomies`, `Search`).
-   Каждому методу: `@group`, `@authenticated` (если требуется), `@subgroup` (для вложенных операций), `@name` (человеческое название).
-   Статусы/заголовки ошибок соответствуют `ProblemDetails` (трейты `Problems`).

## Метаданные Scribe

```php
/**
 * @group Admin ▸ Entries
 * @name List entries
 * @authenticated
 * @queryParam post_type string Example: article. Ограничение по slug типа записи.
 * @queryParam per_page int required Максимум 100. Default: 20.
 * @responseFile status=200 storage/scribe/examples/admin.entries.index.json
 * @responseFile status=401 storage/scribe/examples/errors/unauthorized.json
 */
public function index(IndexEntriesRequest $request): EntryCollection
```

-   Используем `@responseFile` для сложных вложенных объектов (ресурсы).
-   Простые ответы допустимо описывать `@response` с JSON.
-   Для бинарных (`MediaPreviewController@download`) — `@response status=200 file binary`.

## Параметры

-   `@urlParam` для сегментов пути. Формат: `@urlParam entry int required ID записи. Example: 42.`
-   `@queryParam`/`@bodyParam` по данным из FormRequest:
    -   Тип → `string|int|boolean|array|object`.
    -   `required` если есть правило `required|filled`.
    -   `Example:` из `faker()` (константы) или доменного значения.
    -   Перечисления (`in:`) — перечислить значения.
    -   Диапазоны (`min|max|between`) — описать словами.
    -   По умолчанию (`default`) — указать явно.
-   Если FormRequest использует `prepareForValidation()` — описать преобразование.
-   Параметры, зависящие от конфигурации (например, `per_page` лимиты) — ссылаться на `config/stupidcms.php` или доменные docs.

## Ответы

-   Успех: привязка к Resource. Если Resource добавляет `meta`, описываем.
-   Ошибки:
    -   401/403 — `auth`/`can` middleware (использовать готовые примеры в `storage/scribe/examples/errors/*`).
    -   404 — `ProblemDetails` (`NOT_FOUND`) с описанием.
    -   422 — на основе `ValidationException`; документируем структуру `errors`.
    -   429/500 — для rate-limit и общих ошибок (один общий пример).
-   Если ответ кэшируется или содержит доп. заголовки — документируем через `@responseHeader`.

## Кастомные кейсы

-   Batch операции (`EntryTermsController@sync`) — описать формат массива, ограничения на размер.
-   Вебхуки/поллинг — использовать `@endpoint` + ручной пример.
-   Неавтоматизируемые примеры (multipart upload) — `scribe:custom` с ручным сценарием.

## Файлы примеров

-   Расположение: `storage/scribe/examples/{group}/{endpoint}.{status}.json`.
-   Генерация: `php artisan scribe:generate --no-extraction` + ручное наполнение / фиксация снапшотов из тестов.
-   Для бинарных ответов — отдельные заглушки (`.txt`).

## Workflow

```bash
phpstan analyse
php artisan test --testsuite=FeatureApi
php artisan scribe:generate
composer docs:gen
```

-   Проверяем диффы в `docs/_generated/api-docs/*` и `docs/_generated/routes.md`.
-   PR помечаем `requires: docs:gen`.
-   При изменении API контрактов — обновить `docs/30-reference/api.md`.

## Checklist перед PR

-   [ ] У каждого метода есть `@group`, `@name`, `@authenticated` (если нужно).
-   [ ] Все параметры покрыты `@urlParam`/`@queryParam`/`@bodyParam`.
-   [ ] Описаны основные и альтернативные ответы (`200/201/204`, `401/403`, `404`, `422` и бизнесовые).
-   [ ] Примеры соответствуют реальному JSON из Resources.
-   [ ] Добавлены ссылки на доменные документы (`docs/10-concepts/*.md`) где нужно.
-   [ ] Локальные тесты и `scribe:generate` прошли.
