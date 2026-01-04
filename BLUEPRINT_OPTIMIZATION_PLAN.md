# План оптимизации структуры Blueprints

## Цель

Удаление избыточного поля `source_blueprint_id` из таблицы `paths` и оптимизация индексов для улучшения производительности и упрощения архитектуры.

---

## Задачи

### Фаза 1: Подготовка (Задача 1)

#### Задача 1: Обновить все запросы с `whereNull('source_blueprint_id')` на `whereNull('blueprint_embed_id')`

**Файлы:**

-   `app/Services/Blueprint/BlueprintDependencyGraphLoader.php`
-   `app/Services/Blueprint/PathMaterializer.php`
-   `app/Services/Blueprint/PathConflictValidator.php`

**Описание:**

-   Заменить все `whereNull('source_blueprint_id')` на `whereNull('blueprint_embed_id')`
-   Обновить комментарии в коде
-   Обновить PHPDoc, если упоминается `source_blueprint_id`

**Изменения:**

```php
// Было:
->whereNull('source_blueprint_id') // Только собственные пути

// Стало:
->whereNull('blueprint_embed_id') // Только собственные пути (не копии)
```

**Критерии приемки:**

-   Все запросы обновлены
-   Комментарии актуальны
-   Логика не изменилась

---

### Фаза 2: Обновление сервисов (Задачи 2-5)

#### Задача 2: Обновить PathMaterializer для работы без source_blueprint_id

**Файл:** `app/Services/Blueprint/PathMaterializer.php`

**Описание:**

-   Убрать установку `source_blueprint_id` в методе `buildPathStructure()`
-   Обновить метод `batchInsertPaths()` - убрать `source_blueprint_id` из WHERE условий
-   Обновить PHPDoc методов
-   Обновить комментарии

**Изменения:**

```php
// В buildPathStructure() убрать:
'source_blueprint_id' => $sourceBlueprint->id,

// В batchInsertPaths() изменить WHERE:
// Было:
->where('source_blueprint_id', $sourceBlueprint->id)

// Стало:
->where('blueprint_embed_id', $rootEmbed->id)
```

**Критерии приемки:**

-   Код работает без установки `source_blueprint_id`
-   Все запросы обновлены
-   PHPDoc актуален

---

#### Задача 3: Обновить PathConflictValidator для работы без source_blueprint_id

**Файл:** `app/Services/Blueprint/PathConflictValidator.php`

**Описание:**

-   Обновить метод `loadDependencyGraph()` - убрать `whereNull('source_blueprint_id')`
-   Обновить комментарии
-   Обновить PHPDoc

**Изменения:**

```php
// В loadDependencyGraph():
// Было:
->whereNull('source_blueprint_id') // Только собственные пути

// Стало:
->whereNull('blueprint_embed_id') // Только собственные пути (не копии)
```

**Критерии приемки:**

-   Запросы обновлены
-   Логика не изменилась
-   Комментарии актуальны

---

#### Задача 4: Обновить BlueprintDependencyGraphLoader для работы без source_blueprint_id

**Файл:** `app/Services/Blueprint/BlueprintDependencyGraphLoader.php`

**Описание:**

-   Обновить метод `load()` - убрать `whereNull('source_blueprint_id')`
-   Обновить комментарии
-   Обновить PHPDoc

**Изменения:**

```php
// В load():
// Было:
->whereNull('source_blueprint_id')

// Стало:
->whereNull('blueprint_embed_id') // Только собственные пути
```

**Критерии приемки:**

-   Запросы обновлены
-   Логика не изменилась

---

#### Задача 5: Обновить PathResource для работы без source_blueprint_id

**Файл:** `app/Http/Resources/Admin/PathResource.php`

**Описание:**

-   Проверить использование `source_blueprint_id` в ресурсе
-   Если используется - заменить на вычисление через `blueprint_embed_id`
-   Обновить документацию ресурса

**Критерии приемки:**

-   Ресурс работает корректно
-   API ответы не изменились (благодаря accessor)

---

### Фаза 3: Обновление миграций и модели (Задачи 6-8)

#### Задача 6: Обновить миграцию создания таблицы paths

**Файл:** `database/migrations/2025_11_20_115359_create_paths_table.php`

**Описание:**

-   Убрать поле `source_blueprint_id` из создания таблицы
-   Убрать внешний ключ на `source_blueprint_id`
-   Убрать индекс на `source_blueprint_id`
-   Убрать составной индекс `idx_paths_own_paths`
-   Обновить составной индекс `idx_paths_materialization_lookup` - убрать `source_blueprint_id`
-   Обновить комментарии в миграции

**Изменения:**

```php
// Убрать:
$table->foreignId('source_blueprint_id')->nullable()
    ->constrained('blueprints')->restrictOnDelete();

// Убрать:
$table->index('source_blueprint_id');

// Убрать:
$table->index(
    ['blueprint_id', 'source_blueprint_id'],
    'idx_paths_own_paths'
);

// Обновить idx_paths_materialization_lookup:
// Было:
CREATE INDEX idx_paths_materialization_lookup
ON paths (blueprint_id, blueprint_embed_id, source_blueprint_id, full_path(100))

// Стало:
CREATE INDEX idx_paths_materialization_lookup
ON paths (blueprint_id, blueprint_embed_id, full_path(100))
```

**Критерии приемки:**

-   Миграция успешно выполняется
-   Все индексы обновлены
-   Комментарии актуальны

---

#### Задача 7: Обновить модель Path - убрать source_blueprint_id полностью

**Файл:** `app/Models/Path.php`

**Описание:**

-   Убрать метод `sourceBlueprint()` полностью
-   Убрать свойство `source_blueprint_id` из PHPDoc
-   Обновить метод `isCopied()` - использовать только `blueprint_embed_id`
-   Обновить метод `isOwn()` - использовать только `blueprint_embed_id`
-   Обновить все PHPDoc комментарии
-   Убрать `source_blueprint_id` из массива `$guarded` (если там есть)

---

#### Задача 8: Удалить все использования метода sourceBlueprint() в коде

**Файлы:** Все файлы, использующие `sourceBlueprint()`

**Описание:**

-   Найти все использования `->sourceBlueprint()` или `$path->sourceBlueprint`
-   Заменить на `->blueprintEmbed->embeddedBlueprint`
-   Обновить комментарии
-   Обновить PHPDoc

**Поиск использований:**

```bash
grep -r "sourceBlueprint" app/ tests/ database/
```

**Изменения:**

```php
// Было:
$path->sourceBlueprint->code
$path->load('sourceBlueprint');

// Стало:
$path->blueprintEmbed->embeddedBlueprint->code
$path->load('blueprintEmbed.embeddedBlueprint');
```

**Критерии приемки:**

-   Все использования удалены или заменены
-   Код компилируется без ошибок
-   Логика не изменилась

**Изменения:**

```php
// Убрать метод sourceBlueprint():
// Удалить полностью метод sourceBlueprint()

// Обновить isCopied():
public function isCopied(): bool
{
    return $this->blueprint_embed_id !== null;
}

// Обновить isOwn():
public function isOwn(): bool
{
    return $this->blueprint_embed_id === null;
}

// Обновить PHPDoc - убрать source_blueprint_id:
/**
 * @property int $id
 * @property int $blueprint_id Владелец поля
 * @property int|null $blueprint_embed_id К какому embed привязано (если копия)
 * @property int|null $parent_id Родительский path
 * @property string $name Локальное имя поля
 * @property string $full_path Материализованный путь
 * ...
 */
```

**Критерии приемки:**

-   Модель работает корректно
-   Методы обновлены
-   PHPDoc актуален

---

### Фаза 4: Обновление тестов (Задачи 9-11)

#### Задача 9: Обновить unit-тесты сервисов

**Файлы:**

-   `tests/Unit/Services/Blueprint/MaterializationServiceTest.php`
-   `tests/Unit/Services/Blueprint/PathMaterializerTest.php` (если есть)
-   `tests/Unit/Services/Blueprint/PathConflictValidatorTest.php` (если есть)

**Описание:**

-   Убрать проверки `source_blueprint_id` из assertions
-   Обновить проверки на использование `blueprint_embed_id`
-   Обновить моки и фабрики
-   Обновить комментарии в тестах

**Критерии приемки:**

-   Все тесты проходят
-   Покрытие не уменьшилось
-   Комментарии актуальны

---

#### Задача 10: Обновить feature-тесты

**Файлы:**

-   `tests/Feature/Api/Admin/Blueprints/PathSchemasTest.php`
-   `tests/Feature/Api/Admin/Blueprints/BlueprintEmbedControllerTest.php`
-   `tests/Integration/UltraComplexBlueprintSystemTest.php`

**Описание:**

-   Обновить проверки в assertions
-   Убрать проверки `source_blueprint_id` из ответов API
-   Обновить фабрики Path
-   Обновить комментарии

**Критерии приемки:**

-   Все тесты проходят
-   API тесты работают корректно
-   Интеграционные тесты проходят

---

#### Задача 11: Обновить performance-тесты

**Файлы:**

-   `tests/Performance/BlueprintMaterializationPerformanceTest.php`
-   `tests/Performance/BlueprintLoadTest.php`

**Описание:**

-   Обновить тесты для работы без `source_blueprint_id`
-   Проверить, что производительность не ухудшилась
-   Обновить бенчмарки при необходимости

**Критерии приемки:**

-   Тесты проходят
-   Производительность не ухудшилась
-   Бенчмарки актуальны

---

### Фаза 5: Обновление сидеров и документации (Задачи 12-14)

#### Задача 12: Обновить сидеры

**Файлы:**

-   `database/seeders/BlueprintsSeeder.php`
-   Другие сидеры, использующие Path (если есть)

**Описание:**

-   Убрать установку `source_blueprint_id` в фабриках/сидерах
-   Обновить создание тестовых данных
-   Обновить комментарии

**Критерии приемки:**

-   Сидеры работают корректно
-   Тестовые данные создаются правильно
-   Комментарии актуальны

---

#### Задача 13: Обновить документацию и PHPDoc

**Файлы:**

-   Все файлы с упоминанием `source_blueprint_id`
-   `BLUEPRINT_TABLES_STRUCTURE.md`
-   `BLUEPRINT_ARCHITECTURE_REDUNDANCY_ANALYSIS.md`

**Описание:**

-   Обновить документацию структуры таблиц
-   Добавить примечание об удалении поля
-   Обновить диаграммы (если есть)
-   Обновить примеры кода
-   Обновить PHPDoc во всех файлах

**Изменения:**

-   В `BLUEPRINT_TABLES_STRUCTURE.md`:

    -   Убрать `source_blueprint_id` из структуры таблицы `paths`
    -   Обновить описание скопированных полей
    -   Обновить примеры SQL

-   В коде:
    -   Обновить все PHPDoc блоки
    -   Убрать упоминания `source_blueprint_id`
    -   Обновить примеры в комментариях

**Критерии приемки:**

-   Документация актуальна
-   Все PHPDoc обновлены
-   Примеры кода работают

---

#### Задача 14: Финальная проверка и очистка кода

**Файлы:** Все измененные файлы

**Описание:**

-   Выполнить поиск всех упоминаний `source_blueprint_id` в коде
-   Убедиться, что все использования удалены
-   Проверить, что нет "мертвого" кода
-   Запустить статический анализ (PHPStan/Psalm)
-   Проверить форматирование кода (php-cs-fixer)
-   Обновить CHANGELOG (если есть)

**Команды для проверки:**

```bash
# Поиск всех упоминаний
grep -r "source_blueprint_id" app/ tests/ database/ --exclude-dir=vendor

# Поиск использований метода
grep -r "sourceBlueprint" app/ tests/ --exclude-dir=vendor

# Статический анализ
./vendor/bin/phpstan analyse

# Форматирование
./vendor/bin/php-cs-fixer fix
```

**Критерии приемки:**

-   Нет упоминаний `source_blueprint_id` в коде (кроме документации об удалении)
-   Нет использований метода `sourceBlueprint()`
-   Статический анализ проходит без ошибок
-   Код отформатирован
-   CHANGELOG обновлен

---

## Порядок выполнения

### Этап 1: Подготовка (Задача 1)

Выполнить для подготовки запросов.

### Этап 2: Обновление кода (Задачи 2-5)

Выполнить параллельно, так как изменения независимы.

### Этап 3: Миграции и модель (Задачи 6-8)

Выполнить последовательно:

1. Обновить миграцию создания таблицы (Задача 6)
2. Обновить модель Path (Задача 7)
3. Удалить все использования sourceBlueprint() (Задача 8)

### Этап 4: Тесты (Задачи 9-11)

Выполнить параллельно по типам тестов.

### Этап 5: Финализация (Задачи 12-14)

Выполнить последовательно для завершения миграции.

---

## Критерии готовности к деплою

-   [ ] Все тесты проходят (unit, feature, integration, performance)
-   [ ] Миграция протестирована на тестовой БД
-   [ ] Документация обновлена
-   [ ] PHPDoc актуален во всех файлах
-   [ ] Сидеры работают корректно
-   [ ] Производительность не ухудшилась (проверено бенчмарками)
-   [ ] Все использования `source_blueprint_id` и `sourceBlueprint()` удалены

---

## Откат изменений

В случае проблем можно откатить изменения:

1. **Откат миграции:** `php artisan migrate:rollback`
2. **Восстановление кода:** через git revert
3. **Восстановление данных:** поле `source_blueprint_id` можно восстановить через:
    ```sql
    UPDATE paths p
    JOIN blueprint_embeds be ON p.blueprint_embed_id = be.id
    SET p.source_blueprint_id = be.embedded_blueprint_id
    WHERE p.blueprint_embed_id IS NOT NULL;
    ```

---

## Примечания

1. **Breaking changes:** Удаление `source_blueprint_id` - это breaking change. Все клиенты API должны быть обновлены.
2. **Тестирование:** Обязательно протестировать на копии production БД перед деплоем
3. **Мониторинг:** После деплоя мониторить производительность запросов к таблице `paths`
4. **Индексы:** После удаления поля проверить использование индексов и при необходимости оптимизировать
5. **Миграция:** Изменения вносятся напрямую в существующую миграцию создания таблицы, без создания новой миграции

---

## Оценка времени

-   **Задача 1 (Подготовка):** 1-2 часа
-   **Задачи 2-5 (Сервисы):** 6-8 часов
-   **Задачи 6-8 (Миграции и модель):** 4-5 часов
-   **Задачи 9-11 (Тесты):** 8-10 часов
-   **Задачи 12-14 (Финализация):** 2-4 часа

**Итого:** 21-29 часов (2.5-3.5 рабочих дня)

---

## Риски и митигация

### Риск 1: Breaking changes в API

**Митигация:**

-   Уведомить всех пользователей API о breaking changes
-   Обновить версию API (если используется версионирование)
-   Предоставить migration guide

### Риск 2: Ухудшение производительности запросов

**Митигация:**

-   Протестировать на копии production БД
-   Мониторить после деплоя
-   При необходимости добавить индексы

### Риск 3: Ошибки в миграции

**Митигация:**

-   Тщательное тестирование на тестовой БД
-   Проверки существования перед удалением
-   Возможность отката

---

## Чеклист перед коммитом

-   [ ] Все задачи выполнены
-   [ ] Код отформатирован (php-cs-fixer)
-   [ ] Линтер не выдает ошибок
-   [ ] Все тесты проходят
-   [ ] Документация обновлена
-   [ ] PHPDoc актуален
-   [ ] Коммит-сообщение содержит описание изменений
