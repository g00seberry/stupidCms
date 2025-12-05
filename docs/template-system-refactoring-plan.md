# План переработки механизма разрешения шаблонов

## Цели

1. Шаблоны хранятся только в папке `resources/views/templates` и дочерних папках
2. У PostType появляется поле `template` (аналогично `Entry.template_override`)
3. Определение шаблона происходит только по полю `template`, slug больше не используется
4. Если шаблон не задан, используется дефолтный `index`
5. Полное удаление поля `slug` из PostType

## Задачи

### Задача 1: Добавить поле `template` в таблицу `post_types`

**Файл:** `database/migrations/2025_11_06_000010_create_post_types_table.php`

**Изменения:**
- Добавить поле `template` (nullable string, max 255) после поля `name`
- Поле должно быть nullable, так как если не задано - используется дефолтный `index`

**Код:**
```php
$table->string('template')->nullable()->after('name');
```

**Зависимости:** Нет

---

### Задача 2: Создать сервис для валидации путей шаблонов

**Файл:** `app/Domain/View/TemplatePathValidator.php` (новый)

**Описание:**
- Класс для валидации, что шаблон находится в папке `templates` или дочерних
- Метод `validate(string $template): bool` - проверяет, что путь начинается с `templates.`
- Метод `normalize(string $template): string` - нормализует путь (убирает лишние точки, слеши)

**Зависимости:** Нет

---

### Задача 3: Переработать `BladeTemplateResolver` для использования полей `template`

**Файл:** `app/Domain/View/BladeTemplateResolver.php`

**Изменения:**
- Удалить метод `getPostTypeSlug()`
- Удалить логику с проверкой `entry--{postType}--{slug}` и `entry--{postType}`
- Новая логика:
  1. Если `Entry.template_override` задан → использовать его (с валидацией через `TemplatePathValidator`)
  2. Если `PostType.template` задан → использовать его (с валидацией)
  3. Иначе → использовать `index` (дефолтный шаблон)

**Конструктор:**
- Изменить дефолтное значение с `'entry'` на `'templates.index'`

**Зависимости:** Задача 2

---

### Задача 4: Настроить пути views для ограничения шаблонов папкой `templates`

**Файл:** `app/Providers/AppServiceProvider.php` (метод `boot()`)

**Изменения:**
- Добавить регистрацию дополнительного пути views: `resources/views/templates`
- Это позволит Laravel искать шаблоны в этой папке с префиксом `templates.`

**Альтернатива:** Использовать кастомный ViewFinder или настроить через `View::addNamespace()`

**Зависимости:** Нет

---

### Задача 5: Обновить модель `PostType` - добавить поле `template`

**Файл:** `app/Models/PostType.php`

**Изменения:**
- Добавить `'template'` в `$fillable`
- Обновить PHPDoc: добавить `@property string|null $template`
- Удалить `'slug'` из `$fillable` (но пока оставить в миграции для следующей задачи)

**Зависимости:** Задача 1

---

### Задача 6: Удалить поле `slug` из таблицы `post_types`

**Файл:** `database/migrations/2025_11_06_000010_create_post_types_table.php`

**Изменения:**
- Удалить строку `$table->string('slug')->unique();`
- Удалить индекс уникальности для slug

**Зависимости:** Задача 5

---

### Задача 7: Обновить Request классы для PostType

**Файлы:**
- `app/Http/Requests/Admin/StorePostTypeRequest.php`
- `app/Http/Requests/Admin/UpdatePostTypeRequest.php`

**Изменения:**
- Удалить валидацию поля `slug` из `rules()`
- Добавить валидацию поля `template`:
  - `nullable`
  - `string`
  - `max:255`
  - Кастомное правило для проверки через `TemplatePathValidator`
- Удалить сообщения об ошибках для `slug`
- Обновить PHPDoc

**Зависимости:** Задача 2, Задача 6

---

### Задача 8: Обновить `PostTypeResource` - удалить `slug`, добавить `template`

**Файл:** `app/Http/Resources/Admin/PostTypeResource.php`

**Изменения:**
- В методе `toArray()` удалить `'slug' => $this->slug`
- Добавить `'template' => $this->template`
- Обновить PHPDoc

**Зависимости:** Задача 5

---

### Задача 9: Обновить `PostTypeController` - удалить упоминания `slug`

**Файл:** `app/Http/Controllers/Admin/V1/PostTypeController.php`

**Изменения:**
- Удалить `slug` из примеров в PHPDoc комментариях
- Обновить примеры ответов в PHPDoc (убрать `slug`, добавить `template`)
- Проверить логику контроллера на использование `slug` (если есть - удалить)

**Зависимости:** Задача 7, Задача 8

---

### Задача 10: Обновить сидеры - заменить поиск по `slug` на поиск по `id` или `name`

**Файлы:**
- `database/seeders/PostTypesTaxonomiesSeeder.php`
- `database/seeders/BlueprintsSeeder.php`
- `database/seeders/BlueprintEntriesSeeder.php`
- `database/seeders/EntriesSeeder.php`

**Изменения:**
- Заменить `PostType::where('slug', '...')->first()` на поиск по `name` или использование статических ID
- Если используются статические свойства для ID (как в `PostTypesTaxonomiesSeeder`), оставить как есть
- Добавить поле `template` при создании PostType в сидерах (опционально, можно оставить null)

**Зависимости:** Задача 6

---

### Задача 11: Обновить фабрику `PostTypeFactory` - удалить `slug`, добавить `template`

**Файл:** `database/factories/PostTypeFactory.php`

**Изменения:**
- Удалить `'slug' => $this->faker->unique()->slug(2)` из `definition()`
- Добавить `'template' => null` (по умолчанию null, можно переопределить через state)

**Зависимости:** Задача 5

---

### Задача 12: Обновить `EntryToSearchDoc` - заменить `post_type` slug на ID или name

**Файл:** `app/Domain/Search/Transformers/EntryToSearchDoc.php`

**Изменения:**
- В методе `transform()` заменить `'post_type' => (string) $entry->postType?->slug` на `'post_type' => (string) $entry->postType?->id` или `$entry->postType?->name`
- Обновить PHPDoc комментарии
- Обновить документацию в `SearchController` (PHPDoc) - изменить описание параметра `post_type[]`

**Зависимости:** Задача 6

---

### Задача 13: Обновить все тесты - удалить использование `slug` PostType

**Файлы:**
- `tests/Feature/View/BladeTemplateResolverTest.php` - полностью переписать под новую логику
- `tests/Feature/Api/PostTypes/PostTypesTest.php` - удалить проверки slug, добавить проверки template
- `tests/Feature/Models/PostTypeTest.php` - удалить тесты уникальности slug
- `tests/Unit/Models/PostTypeTest.php` - удалить тесты со slug
- Все остальные тесты, где используется `PostType::factory()->create(['slug' => ...])` - удалить slug

**Изменения:**
- В `BladeTemplateResolverTest`:
  - Удалить тесты с `entry--{postType}--{slug}` и `entry--{postType}`
  - Добавить тесты для `PostType.template`
  - Обновить тесты для `Entry.template_override` (добавить валидацию пути)
  - Добавить тест для дефолтного `index`
- В остальных тестах заменить создание PostType без slug

**Зависимости:** Задача 3, Задача 11

---

### Задача 14: Обновить конфигурацию и документацию

**Файлы:**
- `config/view_templates.php` - обновить комментарии, изменить дефолт на `'templates.index'`
- `docs/generated/README.md` - обновить описание (будет перегенерировано)
- Удалить упоминания slug PostType из документации

**Изменения:**
- В `view_templates.php` обновить описание механизма
- В `AppServiceProvider` обновить дефолтное значение при регистрации

**Зависимости:** Задача 3

---

### Задача 15: Создать структуру папок для шаблонов и переместить существующие

**Действия:**
- Создать папку `resources/views/templates/`
- Переместить `resources/views/entry.blade.php` → `resources/views/templates/index.blade.php`
- Обновить содержимое шаблона, если нужно (проверить пути к layouts)
- Удалить старые шаблоны, если они больше не нужны

**Зависимости:** Задача 4

---

## Порядок выполнения

Задачи должны выполняться строго в указанном порядке, так как каждая зависит от предыдущих:

1. Задача 1 (миграция - добавить template)
2. Задача 2 (валидатор путей)
3. Задача 3 (резолвер)
4. Задача 4 (настройка views)
5. Задача 5 (модель)
6. Задача 6 (миграция - удалить slug)
7. Задача 7 (Request классы)
8. Задача 8 (Resource)
9. Задача 9 (Controller)
10. Задача 10 (сидеры)
11. Задача 11 (фабрика)
12. Задача 12 (поиск)
13. Задача 13 (тесты)
14. Задача 14 (конфигурация)
15. Задача 15 (структура папок)

## Важные замечания

1. **Breaking changes:** Все изменения breaking - обратная совместимость не требуется
2. **Миграции:** Исправлять целевые миграции, не создавать корректирующие
3. **Тесты:** Все тесты должны проходить после выполнения всех задач
4. **Валидация:** Шаблоны должны проверяться на принадлежность к папке `templates`
5. **Дефолтный шаблон:** Если ни `Entry.template_override`, ни `PostType.template` не заданы → `templates.index`

## Проверочный чек-лист после выполнения

- [ ] Миграции применены успешно
- [ ] Все тесты проходят
- [ ] Сидеры работают без ошибок
- [ ] API для PostType работает (создание/обновление без slug, с template)
- [ ] Резолвер шаблонов работает корректно
- [ ] Шаблоны находятся только в `resources/views/templates/`
- [ ] Поиск работает (использует ID или name вместо slug)
- [ ] Документация обновлена
- [ ] `php artisan test` проходит
- [ ] `composer scribe:gen` выполнен
- [ ] `php artisan docs:generate` выполнен

