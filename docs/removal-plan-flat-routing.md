# План удаления плоского роутинга Entry (slug-based)

Подробный план по удалению старого способа роутинга Entry через поле `slug` и подготовке к переходу на иерархическую систему роутинга.

**Цель:** Полностью удалить поле `slug` из модели Entry и всю связанную логику, чтобы подготовить систему к переходу на иерархическую маршрутизацию через `route_nodes`.

---

## Общие принципы выполнения

1. **Выполняем пункт** — удаляем/обновляем код
2. **Пишем тесты** — обновляем существующие тесты, удаляем устаревшие
3. **Выполняем тесты** — запускаем `php artisan test` и убеждаемся, что всё работает
4. **Проверяем линтер** — `composer lint` (если настроен)
5. **Обновляем документацию** — PHPDoc, комментарии

**Важно:** После каждого пункта необходимо убедиться, что все тесты проходят, включая существующие.

---

## Пункт 1: Удаление slug из миграций и БД

### Задачи

1. Обновить существующую миграцию `2025_11_06_000020_create_entries_table.php`:

    - Удалить создание колонки `slug` из `Schema::create('entries')`
    - Удалить все триггеры, связанные со slug:
        - `trg_entries_slug_unique_before_ins`
        - `trg_entries_slug_unique_before_upd`
    - Удалить уникальный индекс `entries_unique_active_slug` (MySQL)
    - Удалить уникальный индекс `entries_unique_slug` (SQLite)
    - Удалить generated column `is_active` (если он больше не используется)
    - Оставить только базовую структуру таблицы

2. Обновить существующую миграцию `2025_11_07_095247_add_slug_index_to_entries_table.php`:
    - Удалить весь код из метода `up()` (оставить пустым)
    - Удалить весь код из метода `down()` (оставить пустым)
    - Или удалить файл полностью, если он больше не нужен

### Файлы для изменения

-   `database/migrations/2025_11_06_000020_create_entries_table.php` — удалить slug-логику
-   `database/migrations/2025_11_07_095247_add_slug_index_to_entries_table.php` — очистить или удалить

### Тесты

**Файл:** `tests/Feature/Database/EntriesTableMigrationTest.php` (если существует)

-   Удалить все тесты, проверяющие наличие колонки `slug` в таблице
-   Удалить все тесты, проверяющие триггеры для slug
-   Удалить все тесты, проверяющие индексы для slug

---

## Пункт 2: Удаление slug из модели Entry

### Задачи

1. Удалить поле `slug` из PHPDoc модели `Entry`:

    - Удалить `@property string $slug` из документации

2. Удалить метод `url()` из модели `Entry`:

    - Метод возвращал `"/{$this->slug}"` — удалить полностью
    - Метод будет переопределён позже для работы с `route_nodes`

3. Обновить PHPDoc комментарии:
    - Удалить упоминания slug из описания модели
    - Обновить описание модели (убрать "глобально уникальный slug")

### Файлы для изменения

-   `app/Models/Entry.php` — удалить `@property string $slug`, метод `url()`

### Тесты

**Файл:** `tests/Unit/Models/EntryTest.php`

-   Удалить все тесты, проверяющие поле `slug`
-   Удалить все тесты, проверяющие метод `url()`
-   Удалить все тесты, использующие `'slug' => ...` в фабриках

---

## Пункт 3: Удаление slug из EntryObserver

### Задачи

1. Удалить логику генерации slug из `EntryObserver`:

    - Удалить метод `ensureSlug()`
    - Удалить вызовы `ensureSlug()` из `creating()` и `updating()`
    - Удалить проверку `isDirty(['title', 'slug'])` → оставить только `isDirty('title')` (если нужно)
    - Удалить зависимости: `Slugifier`, `UniqueSlugService`

2. Обновить PHPDoc комментарии:
    - Удалить упоминания генерации slug
    - Обновить описание Observer

### Файлы для изменения

-   `app/Observers/EntryObserver.php` — удалить `ensureSlug()`, зависимости, вызовы

### Тесты

**Файл:** `tests/Unit/Observers/EntryObserverTest.php`

-   Удалить все тесты, проверяющие генерацию slug
-   Удалить все тесты, проверяющие уникальность slug
-   Удалить все тесты, использующие `Slugifier` и `UniqueSlugService`

---

## Пункт 4: Удаление slug из Request валидации

### Задачи

1. Удалить валидацию slug из `StoreEntryRequest`:

    - Удалить правило `'slug' => [...]`
    - Удалить импорты: `UniqueEntrySlug`
    - Удалить упоминания slug из PHPDoc

2. Удалить валидацию slug из `UpdateEntryRequest`:

    - Удалить правило `'slug' => [...]`
    - Удалить импорты: `UniqueEntrySlug`
    - Удалить упоминания slug из PHPDoc

3. Удалить правила валидации:
    - `app/Rules/UniqueEntrySlug.php` — удалить файл полностью

### Файлы для изменения

-   `app/Http/Requests/Admin/StoreEntryRequest.php` — удалить slug валидацию
-   `app/Http/Requests/Admin/UpdateEntryRequest.php` — удалить slug валидацию
-   `app/Rules/UniqueEntrySlug.php` — **удалить файл**

### Тесты

**Файл:** `tests/Unit/Rules/UniqueEntrySlugTest.php` — **удалить файл**

**Файл:** `tests/Feature/Api/Entries/CreateEntryTest.php`

-   Удалить все тесты, проверяющие валидацию slug
-   Удалить все тесты, использующие `'slug' => ...` в запросах
-   Удалить все проверки наличия slug в ответах

**Файл:** `tests/Feature/Api/Entries/UpdateEntryTest.php`

-   Удалить все тесты, проверяющие валидацию slug
-   Удалить все тесты, использующие `'slug' => ...` в запросах
-   Удалить все проверки наличия slug в ответах

---

## Пункт 5: Удаление slug из API Resources

### Задачи

1. Удалить поле `slug` из `EntryResource`:

    - Удалить `'slug' => $this->slug,` из `toArray()`
    - Удалить упоминания slug из PHPDoc

2. Удалить поле `slug` из `SearchHitResource`:

    - Удалить `'slug' => $hit->slug,` из `toArray()`
    - Обновить модель `SearchHit` (если нужно)

3. Обновить документацию API (Scribe):
    - Удалить slug из примеров ответов в контроллерах
    - Обновить `@response` аннотации

### Файлы для изменения

-   `app/Http/Resources/Admin/EntryResource.php` — удалить slug
-   `app/Http/Resources/SearchHitResource.php` — удалить slug
-   `app/Http/Controllers/Admin/V1/EntryController.php` — обновить `@response` аннотации
-   `app/Domain/Search/SearchHit.php` — удалить поле slug (если есть)

### Тесты

**Файл:** `tests/Feature/Api/Entries/ShowEntryTest.php`

-   Удалить все проверки наличия поля `slug` в ответах API
-   Удалить все тесты, использующие `'slug'` в `assertJsonPath` или `assertJson`

**Файл:** `tests/Feature/Api/Entries/ListEntriesTest.php`

-   Удалить все проверки наличия поля `slug` в списке entries
-   Удалить все тесты, использующие `'slug'` в `assertJsonPath` или `assertJson`

**Файл:** `tests/Feature/Api/Public/Search/PublicSearchTest.php`

-   Удалить все проверки наличия поля `slug` в результатах поиска
-   Удалить все тесты, использующие `'slug'` в `assertJsonPath` или `assertJson`

---

## Пункт 6: Удаление slug из поискового индекса

### Задачи

1. Удалить поле `slug` из `EntryToSearchDoc`:

    - Удалить `'slug' => (string) $entry->slug,` из `transform()`
    - Обновить PHPDoc

2. Обновить схему индекса Elasticsearch (если используется):

    - Удалить поле `slug` из маппинга
    - Переиндексировать все документы (команда или миграция)

3. Обновить модель `SearchHit`:
    - Удалить свойство `slug`
    - Обновить конструктор/маппинг

### Файлы для изменения

-   `app/Domain/Search/Transformers/EntryToSearchDoc.php` — удалить slug
-   `app/Domain/Search/SearchHit.php` — удалить поле slug
-   `app/Services/Entry/EntryIndexer.php` — проверить использование slug

### Тесты

**Файл:** `tests/Unit/Domain/Search/Transformers/EntryToSearchDocTest.php`

-   Удалить все тесты, проверяющие наличие поля `slug` в документе
-   Удалить все тесты, использующие `'slug'` в `assertArrayHasKey` или проверках массива

**Файл:** `tests/Unit/Domain/Search/SearchHitTest.php`

-   Удалить все тесты, проверяющие свойство `slug` в `SearchHit`
-   Удалить все тесты, использующие `'slug' => ...` при создании `SearchHit`

---

## Пункт 7: Удаление slug из фабрик и сидеров

### Задачи

1. Удалить генерацию slug из `EntryFactory`:

    - Удалить `'slug' => fake()->unique()->slug(),`
    - Обновить фабрику для создания Entry без slug

2. Проверить сидеры:
    - Удалить установку slug в сидерах (если есть)
    - Обновить тестовые данные

### Файлы для изменения

-   `database/factories/EntryFactory.php` — удалить slug

### Тесты

**Файл:** `tests/Unit/Factories/EntryFactoryTest.php`

-   Удалить все тесты, проверяющие поле `slug` в созданных Entry
-   Удалить все тесты, использующие `'slug' => ...` в фабрике

---

## Пункт 8: Удаление плоского роутинга /{slug}

### Задачи

1. Удалить роут `GET /{slug}` из `routes/web_content.php`:

    - Удалить весь файл или оставить пустым (для будущего multi-level роутинга)
    - Удалить импорты: `ReservedPattern`, `PageController`, `RejectReservedIfMatched`

2. Удалить `PageController`:

    - Удалить метод `show()` или весь контроллер
    - Контроллер будет пересоздан позже для работы с `route_nodes`

3. Удалить `PageShowRequest`:
    - Удалить файл или оставить пустым (для будущего использования)

### Файлы для изменения

-   `routes/web_content.php` — удалить роут `/{slug}`
-   `app/Http/Controllers/PageController.php` — **удалить файл** или оставить пустым
-   `app/Http/Requests/PageShowRequest.php` — **удалить файл** или оставить пустым

### Тесты

**Файл:** `tests/Feature/Web/PagesTest.php` — **удалить файл**

**Файл:** `tests/Feature/Routing/WebContentRoutesTest.php`

-   Удалить все тесты, проверяющие роут `/{slug}`
-   Удалить все тесты, использующие `PageController`
-   Удалить все тесты, проверяющие отображение страниц по slug

---

## Пункт 9: Удаление slug из утилит и сервисов

### Задачи

1. Обновить `UtilsController::slugify()`:

    - **НЕ удалять** — метод может использоваться для других целей (например, для route_nodes)
    - Обновить документацию, если метод больше не используется для Entry

2. Проверить другие сервисы:
    - `app/Services/Entry/EntryIndexer.php` — проверить использование slug
    - Удалить все упоминания slug из сервисов Entry

### Файлы для изменения

-   `app/Http/Controllers/Admin/V1/UtilsController.php` — проверить, обновить документацию
-   `app/Services/Entry/EntryIndexer.php` — удалить использование slug (если есть)

### Тесты

**Файл:** `tests/Feature/Api/Admin/Utils/UtilsTest.php`

-   Удалить все тесты, проверяющие использование `slugify()` для Entry
-   Оставить тесты, проверяющие работу `slugify()` для других целей (если есть)

---

## Пункт 10: Удаление всех тестов, связанных со slug

### Задачи

1. Удалить тесты, специфичные для slug:

    - `tests/Unit/Rules/UniqueEntrySlugTest.php` — **удалить**
    - `tests/Feature/Web/PagesTest.php` — **удалить**
    - Части тестов в других файлах, проверяющие slug

2. Обновить существующие тесты:
    - Удалить проверки slug из `tests/Feature/Models/EntryTest.php`
    - Удалить проверки slug из `tests/Feature/Api/Entries/*Test.php`
    - Удалить проверки slug из `tests/Unit/Models/EntryTest.php`
    - Обновить фабрики в тестах (убрать `'slug' => ...`)

### Файлы для удаления

-   `tests/Unit/Rules/UniqueEntrySlugTest.php` — **удалить**
-   `tests/Feature/Web/PagesTest.php` — **удалить**

### Файлы для обновления

-   `tests/Feature/Models/EntryTest.php` — удалить тесты slug
-   `tests/Feature/Api/Entries/CreateEntryTest.php` — удалить проверки slug
-   `tests/Feature/Api/Entries/UpdateEntryTest.php` — удалить проверки slug
-   `tests/Feature/Api/Entries/ListEntriesTest.php` — удалить проверки slug
-   `tests/Feature/Api/Entries/ShowEntryTest.php` — удалить проверки slug
-   `tests/Feature/Api/Entries/DeleteRestoreEntryTest.php` — удалить проверки slug
-   `tests/Unit/Models/EntryTest.php` — удалить тесты slug
-   Все остальные тесты, использующие slug для Entry

### Тесты

После удаления всех тестов slug, запустить:

```bash
php artisan test
```

Убедиться, что:

-   Все тесты проходят
-   Нет ошибок компиляции
-   Нет упоминаний slug в тестах

---

## Пункт 11: Обновление документации и комментариев

### Задачи

1. Обновить PHPDoc во всех изменённых файлах:

    - Удалить упоминания slug из описаний
    - Обновить примеры использования
    - Обновить `@param`, `@return`, `@throws`

2. Обновить документацию проекта:

    - `docs/routing-system.md` — отметить, что плоский роутинг удалён
    - Обновить примеры в документации
    - Добавить примечание о переходе на иерархическую систему

3. Обновить комментарии в коде:
    - Удалить устаревшие комментарии про slug
    - Обновить описания методов

### Файлы для изменения

-   Все файлы, изменённые в предыдущих пунктах
-   `docs/routing-system.md` — обновить документацию

---

## Пункт 12: Финальная проверка и очистка

### Задачи

1. Поиск всех упоминаний slug в коде:

    ```bash
    # Найти все упоминания slug для Entry
    grep -r "slug" app/ --include="*.php" | grep -i entry
    grep -r "slug" tests/ --include="*.php" | grep -i entry
    ```

2. Удалить неиспользуемые зависимости:

    - Проверить, используются ли `Slugifier` и `UniqueSlugService` где-то ещё
    - Если нет — можно оставить (будут использоваться для route_nodes)

3. Очистить кэш:

    ```bash
    php artisan config:clear
    php artisan route:clear
    php artisan cache:clear
    ```

4. Запустить все тесты:

    ```bash
    php artisan test
    ```

5. Проверить линтер:

    ```bash
    composer lint  # если настроен
    ```

6. Сгенерировать документацию:
    ```bash
    php artisan docs:generate
    composer scribe:gen
    ```

### Чек-лист

-   [ ] Все миграции применены
-   [ ] Все тесты проходят
-   [ ] Линтер не выдаёт ошибок
-   [ ] Документация обновлена
-   [ ] Нет упоминаний slug для Entry в коде
-   [ ] API работает корректно
-   [ ] Поиск работает корректно (без slug)

---

## Дополнительные замечания

### Что НЕ удалять

2. **`app/Domain/Routing/ReservedPattern.php`** — удалён как легаси (не использовался)
3. **`app/Support/Slug/Slugifier.php`** и связанные классы — могут использоваться для route_nodes
4. **`app/Http/Controllers/Admin/V1/UtilsController::slugify()`** — может использоваться для других целей

### Что удалить полностью

1. **`app/Rules/UniqueEntrySlug.php`** — специфично для Entry
2. **`app/Http/Controllers/PageController.php`** — будет пересоздан для route_nodes
3. **`app/Http/Requests/PageShowRequest.php`** — будет пересоздан для route_nodes
4. **`routes/web_content.php`** — будет пересоздан для multi-level роутинга
5. Все тесты, специфичные для плоского роутинга Entry

### Порядок выполнения

**Критически важно:** Пункты должны выполняться строго в указанном порядке, так как каждый пункт зависит от предыдущих.

1. **Пункт 1** — База данных (фундамент)
2. **Пункт 2** — Модель (работа с данными)
3. **Пункт 3** — Observer (логика сохранения)
4. **Пункт 4** — Валидация (защита данных)
5. **Пункт 5** — API Resources (сериализация)
6. **Пункт 6** — Поиск (индексация)
7. **Пункт 7** — Фабрики (тестовые данные)
8. **Пункт 8** — Роутинг (публичный доступ)
9. **Пункт 9** — Утилиты (вспомогательные сервисы)
10. **Пункт 10** — Тесты (проверка функционала)
11. **Пункт 11** — Документация (описание системы)
12. **Пункт 12** — Финальная проверка (гарантия качества)

---

## Команды после каждого пункта

```bash
# Запуск тестов
php artisan test

# Проверка линтера
composer lint  # если настроен

# Очистка кэша
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Генерация документации
php artisan docs:generate
composer scribe:gen
```

---

**Дата создания:** 2025-12-05  
**Версия:** 1.0  
**Статус:** Готов к выполнению
