# План миграции: перенос `is_required` из корня `path` в `validation_rules`

## Цель

Перенести флаг обязательности поля из отдельного поля `is_required` в структуру `validation_rules` как `required: true/false`.

## Текущее состояние

### Структура данных

**Модель Path:**

-   `is_required` (boolean) — отдельное поле в таблице `paths`
-   `validation_rules` (JSON, nullable) — массив правил валидации

**Пример текущей структуры:**

```php
Path::create([
    'name' => 'title',
    'data_type' => 'string',
    'is_required' => true,  // ← отдельное поле
    'validation_rules' => [
        'min' => 1,
        'max' => 500,
    ],
]);
```

### Использование `is_required`

1. **PathValidationRulesConverter** (`app/Domain/Blueprint/Validation/PathValidationRulesConverter.php`)

    - Метод `convert()` принимает `$isRequired` как отдельный параметр
    - Строки 59-65: создаёт `RequiredRule` или `NullableRule` на основе `$isRequired`

2. **EntryValidationService** (`app/Domain/Blueprint/Validation/EntryValidationService.php`)

    - Строка 57: загружает `is_required` из БД
    - Строки 79-83: использует `$path->is_required` для массивов
    - Строка 147: передаёт `$path->is_required` в конвертер

3. **API Request классы:**

    - `StorePathRequest` (строка 61): валидирует `is_required` как `boolean`
    - `UpdatePathRequest` (строка 61): валидирует `is_required` как `boolean`

4. **Сервисы:**

    - `BlueprintStructureService::createPath()` (строка 149): принимает `is_required` в массиве данных
    - `PathMaterializer` (строка 126): загружает `is_required` из БД

5. **Ресурсы:**

    - `PathResource` (строка 44): возвращает `is_required` в API ответе

6. **Тесты и сидеры:**
    - Множество мест используют `is_required` при создании Path

## Целевое состояние

### Структура данных

**Модель Path:**

-   `is_required` (boolean) — **полностью удаляется**
-   `validation_rules` (JSON, nullable) — содержит `required: true/false`

**Пример целевой структуры:**

```php
Path::create([
    'name' => 'title',
    'data_type' => 'string',
    'validation_rules' => [
        'required' => true,  // ← перенесено в validation_rules
        'min' => 1,
        'max' => 500,
    ],
]);
```

## План миграции

### Этап 1: Подготовка — добавление поддержки `required` в `validation_rules`

#### 1.1. Обновить `PathValidationRulesConverterInterface` и `PathValidationRulesConverter`

**Файлы:**

-   `app/Domain/Blueprint/Validation/PathValidationRulesConverterInterface.php`
-   `app/Domain/Blueprint/Validation/PathValidationRulesConverter.php`

**Изменения:**

-   Удалить параметр `$isRequired` из метода `convert()` в интерфейсе и реализации
-   Обновить PHPDoc: убрать упоминание `is_required`, добавить описание `required` в `validation_rules`
-   Читать `required` напрямую из `$validationRules`
-   Если `required` не указан в `validation_rules`, использовать значение по умолчанию `false`

**Логика:**

```php
// Извлекаем required из validation_rules
$isRequired = $validationRules['required'] ?? false;
```

#### 1.2. Обновить `EntryValidationServiceInterface` и `EntryValidationService`

**Файлы:**

-   `app/Domain/Blueprint/Validation/EntryValidationServiceInterface.php`
-   `app/Domain/Blueprint/Validation/EntryValidationService.php`

**Изменения:**

-   Обновить PHPDoc в интерфейсе (строка 27): заменить упоминание `is_required` на `required` в `validation_rules`
-   Удалить загрузку `is_required` из БД (строка 57)
-   Для `cardinality: 'one'` (строка 147): убрать параметр `$isRequired` из вызова `convert()`
-   Для `cardinality: 'many'` (строка 79): извлекать `required` из `validation_rules` вместо `$path->is_required`

**Логика:**

```php
// Для массивов
$isRequired = $path->validation_rules['required'] ?? false;

// Для одиночных полей
$fieldRules = $this->converter->convert(
    $path->validation_rules,
    $path->data_type,
    ValidationConstants::CARDINALITY_ONE,
    $fieldName
);
```

### Этап 2: Миграция данных

#### 2.1. Создать миграцию для переноса данных

**Файл:** `database/migrations/YYYY_MM_DD_HHMMSS_migrate_is_required_to_validation_rules.php`

**Задачи:**

1. Для каждого Path, где `is_required = true` и `validation_rules` не содержит `required`:
    - Добавить `required: true` в `validation_rules`
2. Для каждого Path, где `is_required = false` и `validation_rules` не содержит `required`:
    - Добавить `required: false` в `validation_rules` (явно указываем для ясности)
3. Если `validation_rules` уже содержит `required`, оставить как есть (приоритет у существующих данных)

**SQL логика:**

```sql
UPDATE paths
SET validation_rules = JSON_SET(
    COALESCE(validation_rules, '{}'),
    '$.required',
    CASE WHEN is_required = 1 THEN JSON_TRUE() ELSE JSON_FALSE() END
)
WHERE JSON_EXTRACT(validation_rules, '$.required') IS NULL;
```

**Laravel код:**

```php
Path::query()
    ->whereNull(DB::raw("JSON_EXTRACT(validation_rules, '$.required')"))
    ->chunkById(100, function ($paths) {
        foreach ($paths as $path) {
            $rules = $path->validation_rules ?? [];
            $rules['required'] = $path->is_required;
            $path->update(['validation_rules' => $rules]);
        }
    });
```

### Этап 3: Обновление API и сервисов

#### 3.1. Обновить `StorePathRequest`

**Файл:** `app/Http/Requests/Admin/Path/StorePathRequest.php`

**Изменения:**

-   Удалить валидацию `is_required` (строка 61)
-   Добавить валидацию `required` внутри `validation_rules`
-   Обновить PHPDoc

**Новые правила:**

```php
'validation_rules' => ['nullable', 'array'],
'validation_rules.required' => ['sometimes', 'boolean'],
```

#### 3.2. Обновить `UpdatePathRequest`

**Файл:** `app/Http/Requests/Admin/Path/UpdatePathRequest.php`

**Изменения:**

-   Аналогично `StorePathRequest`

#### 3.3. Обновить `BlueprintStructureService`

**Файл:** `app/Services/Blueprint/BlueprintStructureService.php`

**Изменения:**

-   Метод `createPath()` (строка 129):
    -   Удалить обработку `is_required` из массива `$data`
    -   Удалить установку `is_required` при создании Path (строка 149)
-   Метод `updatePath()` (строка 171):
    -   Удалить обработку `is_required` из массива `$data`
-   Обновить PHPDoc методов, убрав `is_required` из описания параметров

#### 3.4. Обновить `PathMaterializer`

**Файл:** `app/Services/Blueprint/PathMaterializer.php`

**Изменения:**

-   Метод `buildPathStructure()` (строка 189):
    -   Удалить строку `'is_required' => $source->is_required`
    -   `validation_rules` уже копируется на строке 193, поэтому `required` будет включен автоматически
-   Метод `loadOwnPaths()` (строка 126):
    -   Убрать `is_required` из select (можно сделать в Этапе 5, но лучше сразу)

**Важно:** После миграции данных (Этап 2) все Path будут иметь `required` в `validation_rules`, поэтому при копировании Path через `buildPathStructure()` `validation_rules` будет содержать `required`, и отдельное поле `is_required` не нужно.

#### 3.5. Обновить `PathResource`

**Файл:** `app/Http/Resources/Admin/PathResource.php`

**Изменения:**

-   Удалить `is_required` из ответа (строка 44)
-   `validation_rules` уже возвращается в ответе, поэтому `required` будет доступен через него

#### 3.6. Обновить `PathController`

**Файл:** `app/Http/Controllers/Admin/V1/PathController.php`

**Изменения:**

-   Обновить PHPDoc комментарии для Scribe:
    -   Строка 54: убрать `is_required` из примера ответа `index()`
    -   Строка 90: убрать `@bodyParam is_required` из `store()`
    -   Строка 103: убрать `is_required` из примера ответа `store()`
    -   Строка 142: убрать `is_required` из примера ответа `show()`
    -   Строка 172: убрать `@bodyParam is_required` из `update()`

#### 3.7. Обновить `BlueprintController`

**Файл:** `app/Http/Controllers/Admin/V1/BlueprintController.php`

**Изменения:**

-   Метод `buildSchema()` (строка 442): заменить `$path->is_required` на `$path->validation_rules['required'] ?? false`
-   Обновить PHPDoc комментарии для Scribe:
    -   Строка 352: убрать `"required": true` из примера ответа `schema()`
    -   Строка 366: убрать `"required": true` из примера ответа `schema()`

### Этап 4: Обновление тестов и документации

#### 4.1. Обновить тесты

**Файлы для обновления:**

-   `tests/Unit/Domain/Blueprint/Validation/PathValidationRulesConverterTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/EntryValidationServiceTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/BlueprintContentValidatorTest.php`
-   `tests/Feature/Api/Admin/Blueprints/PathSchemasTest.php`
-   `tests/Feature/Api/Admin/Blueprints/BlueprintControllerTest.php`
-   `tests/Feature/Api/Entries/EntryValidationTest.php`
-   `tests/Feature/Api/Entries/EntryValidationAdvancedTest.php`
-   `tests/Feature/Api/Entries/EntryValidationAdditionalTest.php`
-   `tests/Performance/BlueprintLoadTest.php`
-   `tests/Integration/UltraComplexBlueprintSystemTest.php`
-   Все тесты, создающие Path с `is_required`

**Изменения:**

-   Заменить `is_required => true` на `validation_rules => ['required' => true]`
-   Обновить assertions для проверки `validation_rules['required']`
-   Обновить все вызовы `convert()` в тестах, убрав параметр `$isRequired`

#### 4.2. Обновить сидеры и фабрики

**Файлы:**

-   `database/seeders/BlueprintsSeeder.php`
-   `database/factories/PathFactory.php`

**Изменения:**

-   `BlueprintsSeeder`: заменить все `is_required => true/false` на `validation_rules => ['required' => true/false]`
-   `PathFactory`: заменить `is_required => false` (строка 32) на `validation_rules => ['required' => false]` или убрать (если false по умолчанию)

#### 4.3. Обновить документацию

**Файлы:**

-   `docs/blueprint-validation-system.md`
-   `docs/blueprint-validation-frontend.md`

**Изменения:**

-   Обновить примеры: убрать `is_required`, добавить `required` в `validation_rules`
-   Обновить описание структуры Path

### Этап 5: Удаление поля `is_required` из БД и модели

#### 5.1. Создать миграцию для удаления колонки

**Миграция:** `database/migrations/YYYY_MM_DD_HHMMSS_remove_is_required_from_paths.php`

**Задачи:**

1. Удалить колонку `is_required` из таблицы `paths` (включая `->default(false)` из миграции создания таблицы, строка 26)
2. Удалить из `$fillable` в модели `Path` (строка 51)
3. Удалить из `$casts` в модели `Path` (строка 73)
4. Удалить из PHPDoc модели `Path` (строка 24)

## Чек-лист выполнения

### Этап 1: Подготовка

-   [ ] Обновить `PathValidationRulesConverterInterface` (удалить параметр `$isRequired`, обновить PHPDoc)
-   [ ] Обновить `PathValidationRulesConverter` для чтения `required` из `validation_rules`
-   [ ] Обновить `EntryValidationServiceInterface` (обновить PHPDoc)
-   [ ] Обновить `EntryValidationService` для использования `required` из `validation_rules`
-   [ ] Написать тесты для новой логики

### Этап 2: Миграция данных

-   [ ] Создать миграцию для переноса `is_required` → `validation_rules['required']`
-   [ ] Протестировать миграцию на тестовых данных
-   [ ] Запустить миграцию на dev окружении

### Этап 3: Обновление API

-   [ ] Обновить `StorePathRequest` (убрать `is_required`, добавить `validation_rules.required`)
-   [ ] Обновить `UpdatePathRequest`
-   [ ] Обновить `BlueprintStructureService` (удалить обработку `is_required`)
-   [ ] Обновить `PathMaterializer` (заменить `$source->is_required` на `validation_rules['required']` в `buildPathStructure()`)
-   [ ] Обновить `PathResource` (удалить `is_required` из ответа)
-   [ ] Обновить `PathController` (убрать `is_required` из PHPDoc комментариев Scribe)
-   [ ] Обновить `BlueprintController` (заменить `$path->is_required` на `validation_rules['required']` в `buildSchema()`)

### Этап 4: Тесты и документация

-   [ ] Обновить все тесты (заменить `is_required` на `validation_rules['required']`, убрать параметр `$isRequired` из вызовов `convert()`)
-   [ ] Обновить `BlueprintsSeeder`
-   [ ] Обновить `PathFactory`
-   [ ] Обновить документацию

### Этап 5: Удаление поля `is_required`

-   [ ] Создать миграцию для удаления колонки `is_required` из таблицы `paths`
-   [ ] Удалить `is_required` из `$fillable` в модели `Path`
-   [ ] Удалить `is_required` из `$casts` в модели `Path`
-   [ ] Удалить `is_required` из PHPDoc модели `Path`
-   [ ] Обновить все места, где используется `is_required`:
    -   `PathMaterializer` (строка 126) — убрать из select
    -   `BlueprintDependencyGraphLoader` (строка 77) — убрать из select
    -   Все другие места, где загружается или используется `is_required`

## Риски и меры предосторожности

1. **Потеря данных при миграции:**

    - Создать бэкап БД перед миграцией
    - Тестировать миграцию на копии production данных

2. **Breaking changes в API:**

    - Обновить API документацию (Scribe)
    - Уведомить фронтенд-разработчиков о breaking changes
    - Фронтенд должен обновить все запросы, убрав `is_required` и добавив `required` в `validation_rules`

3. **Производительность:**

    - Миграция данных может занять время на больших таблицах
    - Использовать chunk обработку

4. **Тесты:**
    - Убедиться, что все тесты проходят после изменений
    - Обновить все тесты, использующие `is_required`

## Порядок выполнения команд

После выполнения каждого этапа:

```bash
# Запустить тесты
php artisan test

# Обновить документацию API
composer scribe:gen

# Обновить навигационную документацию
php artisan docs:generate
```

## Примеры изменений

### До миграции:

```php
Path::create([
    'name' => 'title',
    'data_type' => 'string',
    'is_required' => true,
    'validation_rules' => ['min' => 1, 'max' => 500],
]);
```

### После миграции:

```php
Path::create([
    'name' => 'title',
    'data_type' => 'string',
    'validation_rules' => [
        'required' => true,
        'min' => 1,
        'max' => 500,
    ],
]);
```

### API запрос (до):

```json
{
    "name": "title",
    "data_type": "string",
    "is_required": true,
    "validation_rules": {
        "min": 1,
        "max": 500
    }
}
```

### API запрос (после):

```json
{
    "name": "title",
    "data_type": "string",
    "validation_rules": {
        "required": true,
        "min": 1,
        "max": 500
    }
}
```
