# План рефакторинга: PostType slug → ID

## Цель

Изменить использование PostType: slug должен использоваться **только для определения шаблона вывода entry**, все остальное взаимодействие должно происходить через **id**. 

Также изменить уникальность entry.slug с локальной (в рамках post_type_id) на **глобальную**.

## Текущая проблема

1. В миграции `create_entries_table.php` есть костыльный триггер, который ищет post_type по slug = 'page' для проверки уникальности
2. Уникальность slug привязана к post_type_id, что неправильно - должна быть глобальной
3. PostType slug используется во многих местах, где должен использоваться id
4. PostType с slug 'page' может вообще не существовать в системе

## Архитектурные изменения

### База данных

1. **entries таблица:**
   - Убрать уникальный индекс `entries_unique_active_slug` по `(post_type_id, slug, is_active)`
   - Создать уникальный индекс по `(slug, is_active)` - глобальная уникальность
   - Удалить триггеры `trg_entries_pages_slug_unique_before_ins` и `trg_entries_pages_slug_unique_before_upd`
   - Оставить проверку зарезервированных путей в триггерах (но убрать привязку к post_type)

2. **form_configs таблица:**
   - Заменить `post_type_slug` (string) на `post_type_id` (foreignId)
   - Обновить уникальный индекс с `(post_type_slug, blueprint_id)` на `(post_type_id, blueprint_id)`
   - Обновить индекс с `post_type_slug` на `post_type_id`

### API изменения

Все API endpoints, которые используют `post_type` как slug, должны использовать `post_type_id` как integer.

## Последовательность задач

### Этап 1: Исправление существующих миграций

**Задача 1.1: Исправить миграцию create_entries_table.php**
- **Файл:** `database/migrations/2025_11_06_000020_create_entries_table.php`
- **Действия:**
  - Удалить уникальный индекс `entries_unique_active_slug` по `(post_type_id, slug, is_active)`
  - Создать новый уникальный индекс по `(slug, is_active)` для MySQL/MariaDB - глобальная уникальность
  - Для SQLite: заменить уникальный индекс с `(post_type_id, slug)` на `(slug)`
  - Удалить триггеры `trg_entries_pages_slug_unique_before_ins` и `trg_entries_pages_slug_unique_before_upd`
  - Создать новые триггеры `trg_entries_slug_unique_before_ins` и `trg_entries_slug_unique_before_upd`, которые проверяют только:
    - Глобальную уникальность slug (все записи, кроме удаленных)
    - Зарезервированные пути
  - Убрать из триггеров все упоминания `post_type.slug = 'page'`

**Задача 1.2: Исправить миграцию create_form_configs_table.php**
- **Файл:** `database/migrations/2025_11_24_094845_create_form_configs_table.php`
- **Действия:**
  - Заменить `post_type_slug` (string) на `post_type_id` (foreignId)
  - Обновить уникальный индекс с `(post_type_slug, blueprint_id)` на `(post_type_id, blueprint_id)`
  - Обновить индекс с `post_type_slug` на `post_type_id`
  - Добавить foreign key constraint на `post_types.id` с `restrictOnDelete`

### Этап 2: Обновление правил валидации

**Задача 2.1: Изменить UniqueEntrySlug на глобальную проверку**
- **Файл:** `app/Rules/UniqueEntrySlug.php`
- **Действия:**
  - Убрать параметр `$postTypeSlug` из конструктора
  - Изменить логику проверки: проверять уникальность slug глобально (все записи, кроме исключенной)
  - Убрать поиск PostType по slug
  - Обновить PHPDoc комментарии
  - Обновить сообщение об ошибке

**Задача 2.2: Обновить StoreEntryRequest**
- **Файл:** `app/Http/Requests/Admin/StoreEntryRequest.php`
- **Действия:**
  - Изменить правило валидации `post_type`: с `exists:post_types,slug` на `required|integer|exists:post_types,id`
  - Убрать использование `$postTypeSlug` в UniqueEntrySlug
  - Обновить BlueprintValidationTrait: вместо получения PostType по slug, получать по id
  - Обновить PHPDoc комментарии
  - Обновить сообщения валидации

**Задача 2.3: Обновить UpdateEntryRequest**
- **Файл:** `app/Http/Requests/Admin/UpdateEntryRequest.php`
- **Действия:**
  - Убрать использование `$postTypeSlug` в UniqueEntrySlug (уже есть post_type_id в entry)
  - Обновить PHPDoc комментарии

**Задача 2.4: Обновить IndexEntriesRequest**
- **Файл:** `app/Http/Requests/Admin/IndexEntriesRequest.php`
- **Действия:**
  - Изменить правило валидации `post_type`: с `exists:post_types,slug` на `nullable|integer|exists:post_types,id`
  - Обновить PHPDoc комментарии
  - Обновить сообщения валидации

### Этап 3: Обновление контроллеров

**Задача 3.1: Обновить EntryController**
- **Файл:** `app/Http/Controllers/Admin/V1/EntryController.php`
- **Действия:**
  - В методе `index()`: изменить фильтрацию по post_type - использовать `where('post_type_id', $validated['post_type'])` вместо `whereHas('postType', fn($q) => $q->where('slug', ...))`
  - В методе `store()`: получать PostType по id вместо slug: `PostType::findOrFail($validated['post_type'])`
  - В методе `generateUniqueSlug()`: убрать параметр `$postTypeSlug`, проверять уникальность глобально
  - Обновить PHPDoc комментарии (@queryParam, @bodyParam)
  - Обновить примеры ответов в PHPDoc

**Задача 3.2: Обновить UtilsController**
- **Файл:** `app/Http/Controllers/Admin/V1/UtilsController.php`
- **Действия:**
  - Изменить параметр `postType` на `post_type_id` (integer)
  - Убрать проверку уникальности в рамках post_type
  - Проверять только глобальную уникальность slug
  - Обновить PHPDoc комментарии

### Этап 4: Обновление моделей

**Задача 4.1: Обновить модель Entry**
- **Файл:** `app/Models/Entry.php`
- **Действия:**
  - Изменить `scopeOfType()`: принимать `int $postTypeId` вместо `string $postTypeSlug`, использовать `where('post_type_id', $postTypeId)`
  - Пересмотреть метод `url()`: так как slug теперь глобально уникален, все URL должны быть плоскими (`/{slug}`). Убрать зависимость от post_type slug, так как slug глобально уникален
  - Обновить PHPDoc комментарии (особенно описание свойства `slug` - теперь "Уникальный slug записи глобально")

**Задача 4.2: Обновить модель FormConfig**
- **Файл:** `app/Models/FormConfig.php`
- **Действия:**
  - Заменить `post_type_slug` на `post_type_id` в fillable
  - Добавить связь `belongsTo(PostType::class)`
  - Обновить PHPDoc комментарии

### Этап 5: Обновление ресурсов и трансформеров

**Задача 5.1: Обновить EntryResource**
- **Файл:** `app/Http/Resources/Admin/EntryResource.php`
- **Действия:**
  - Решить: оставлять ли `post_type` (slug) в ответе API для обратной совместимости или убрать
  - Если оставить: добавить `post_type_id` в ответ, slug оставить для совместимости
  - Если убрать: заменить `post_type` на `post_type_id` (integer)
  - **Примечание:** Согласно требованиям, обратная совместимость не нужна, поэтому можно убрать slug

**Задача 5.2: Обновить EntryToSearchDoc (поисковый индекс)**
- **Файл:** `app/Domain/Search/Transformers/EntryToSearchDoc.php`
- **Действия:**
  - Решить: оставлять ли `post_type` (slug) в поисковом индексе или заменить на id
  - Если оставить slug: ничего не менять (slug может быть полезен для фильтрации в поиске)
  - Если заменить: добавить `post_type_id` и убрать `post_type` slug
  - **Примечание:** Slug в поисковом индексе может быть полезен для фильтрации, но это зависит от требований

### Этап 6: Обновление FormConfig контроллера и запросов

**Задача 6.1: Обновить FormConfigController**
- **Файл:** `app/Http/Controllers/Admin/V1/FormConfigController.php`
- **Действия:**
  - Найти все использования `post_type_slug` и заменить на `post_type_id`
  - Обновить валидацию: использовать `exists:post_types,id`
  - Обновить PHPDoc комментарии

**Задача 6.2: Обновить StoreFormConfigRequest и UpdateFormConfigRequest**
- **Файлы:** `app/Http/Requests/Admin/FormPreset/*.php`
- **Действия:**
  - Заменить валидацию `post_type_slug` на `post_type_id` (integer)
  - Обновить PHPDoc комментарии

**Задача 6.3: Обновить FormConfigResource**
- **Файл:** `app/Http/Resources/Admin/FormConfigResource.php`
- **Действия:**
  - Заменить `post_type_slug` на `post_type_id` в ответе
  - Обновить PHPDoc комментарии

### Этап 7: Обновление вспомогательных классов

**Задача 7.1: Обновить BlueprintValidationTrait**
- **Файл:** `app/Http/Requests/Admin/Concerns/BlueprintValidationTrait.php`
- **Действия:**
  - Изменить получение PostType: вместо `where('slug', $postTypeSlug)` использовать `find($postTypeId)` или `where('id', $postTypeId)`
  - Обновить PHPDoc комментарии

**Задача 7.2: Обновить FormConfigBlueprintRule (если используется)**
- **Файл:** `app/Rules/FormConfigBlueprintRule.php` (если существует)
- **Действия:**
  - Найти все использования `post_type_slug` и заменить на `post_type_id`

### Этап 8: Обновление тестов

**Задача 8.1: Обновить тесты Entry**
- **Файлы:** 
  - `tests/Unit/Models/EntryTest.php`
  - `tests/Feature/Models/EntryTest.php`
  - `tests/Feature/Api/Entries/*.php`
  - `tests/Unit/Rules/UniqueEntrySlugTest.php`
- **Действия:**
  - Заменить все использования `post_type` (slug) на `post_type_id` (id)
  - Обновить тесты уникальности slug: проверять глобальную уникальность
  - Убрать тесты, которые проверяют уникальность в рамках post_type

**Задача 8.2: Обновить тесты FormConfig**
- **Файл:** `tests/Feature/Api/Admin/FormConfig/FormConfigTest.php`
- **Действия:**
  - Заменить все использования `post_type_slug` на `post_type_id`
  - Обновить фабрики и сидеры

**Задача 8.3: Обновить тесты UtilsController**
- **Файл:** `tests/Feature/Api/Admin/Utils/UtilsTest.php`
- **Действия:**
  - Изменить параметр `postType` на `post_type_id`
  - Обновить проверки уникальности slug

**Задача 8.4: Обновить другие тесты**
- **Файлы:** Все тесты, которые используют PostType
- **Действия:**
  - Найти и заменить все использования `post_type` (slug) на `post_type_id` (id)
  - Обновить фабрики и сидеры

### Этап 9: Обновление фабрик и сидеров

**Задача 9.1: Обновить фабрики**
- **Файлы:** Все фабрики, которые создают Entry или FormConfig
- **Действия:**
  - Заменить использование `post_type_slug` на `post_type_id`
  - Убедиться, что фабрики создают PostType и используют его id

**Задача 9.2: Обновить сидеры**
- **Файлы:** 
  - `database/seeders/EntriesSeeder.php`
  - `database/seeders/BlueprintEntriesSeeder.php`
  - Другие сидеры
- **Действия:**
  - Заменить `PostType::where('slug', ...)->first()` на `PostType::find(...)` или использование уже созданных объектов

### Этап 10: Обновление документации и комментариев

**Задача 10.1: Обновить PHPDoc комментарии**
- **Действия:**
  - Во всех измененных файлах обновить PHPDoc комментарии
  - Убрать упоминания о slug в контексте PostType (кроме шаблонов)
  - Добавить описания изменений

**Задача 10.2: Обновить документацию API (Scribe)**
- **Действия:**
  - После всех изменений запустить `composer scribe:gen`
  - Проверить, что документация корректна

### Этап 11: Финальная проверка

**Задача 11.1: Проверить BladeTemplateResolver**
- **Файл:** `app/Domain/View/BladeTemplateResolver.php`
- **Действия:**
  - Убедиться, что использование PostType slug здесь **остается** (это единственное место, где slug используется правильно - для определения шаблона)
  - Ничего не менять в этом файле

**Задача 11.2: Проверить EntryToSearchDoc**
- **Файл:** `app/Domain/Search/Transformers/EntryToSearchDoc.php`
- **Действия:**
  - Решить, оставлять ли `post_type` (slug) в поисковом индексе
  - Если оставить - ничего не менять (slug может быть полезен для фильтрации в поиске)
  - Если заменить - добавить `post_type_id` и убрать slug

**Задача 11.5: Проверить Entry::url() и тесты**
- **Файлы:** 
  - `app/Models/Entry.php` (метод url())
  - `tests/Unit/Models/EntryTest.php` (тесты url())
  - `tests/Feature/Models/EntryTest.php` (тесты url())
- **Действия:**
  - Убедиться, что метод `url()` возвращает плоский URL `/{slug}` (так как slug глобально уникален)
  - Обновить тесты, которые проверяют URL: убрать проверки иерархических URL типа `/blog/my-post`, оставить только плоские `/my-post`

**Задача 11.3: Запустить все тесты**
- **Действия:**
  - `php artisan test`
  - Исправить все упавшие тесты

**Задача 11.4: Обновить документацию**
- **Действия:**
  - `composer scribe:gen`
  - `php artisan docs:generate`

## Критические моменты

1. **Уникальность slug:** После изменения уникальности slug станет глобальной. Это означает, что нельзя будет создать две записи с одинаковым slug, даже если они разных типов.

2. **URL структура:** После рефакторинга все entry будут иметь глобально уникальные slug. Нужно решить, должны ли все URL быть плоскими (`/{slug}`) или сохранить иерархическую структуру (`/{post_type}/{slug}`). Согласно требованиям, slug используется только для шаблонов, поэтому URL могут быть плоскими.

3. **Обратная совместимость:** Согласно требованиям, обратная совместимость не нужна. Это означает, что API будет возвращать `post_type_id` вместо `post_type` slug.

4. **Миграция данных:** 
   - Для `form_configs` нужно будет заполнить `post_type_id` из `post_type_slug` через JOIN перед удалением старой колонки
   - Для `entries` данные не меняются, меняются только индексы и триггеры

## Порядок выполнения

Задачи должны выполняться последовательно, так как они зависят друг от друга:

1. **Этап 1** - исправление существующих миграций
2. **Этап 2** - обновление валидации (зависит от миграций)
3. **Этап 3** - обновление контроллеров (зависит от валидации)
4. **Этап 4** - обновление моделей (зависит от миграций)
5. **Этап 5** - обновление ресурсов (зависит от моделей)
6. **Этап 6** - обновление FormConfig (зависит от миграций и моделей)
7. **Этап 7** - обновление вспомогательных классов (зависит от моделей)
8. **Этап 8** - обновление тестов (зависит от всех изменений)
9. **Этап 9** - обновление фабрик и сидеров (можно параллельно с тестами)
10. **Этап 10** - обновление документации (после всех изменений)
11. **Этап 11** - финальная проверка

## Файлы, которые НЕ нужно изменять

1. `app/Domain/View/BladeTemplateResolver.php` - здесь slug используется правильно (для шаблонов)
2. `app/Http/Controllers/PageController.php` - ищет entry по slug напрямую, не зависит от post_type

## Риски

1. **Нарушение работы существующего API:** После изменений клиенты API должны будут использовать `post_type_id` вместо `post_type` slug. Это breaking change.

2. **Миграция данных:** При миграции `form_configs` нужно убедиться, что все `post_type_slug` могут быть преобразованы в `post_type_id` (т.е. все PostType существуют).

3. **Производительность:** После изменения индексов нужно проверить, что запросы работают эффективно.

