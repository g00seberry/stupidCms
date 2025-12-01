# План удаления типа `date` из системы

## Цель
Удалить тип данных `date` из системы, оставив только `datetime`. Обратная совместимость не требуется.

## Обоснование
- Тип `date` уже хранится в `value_datetime` в таблице `doc_values`
- Дублирование типов усложняет поддержку
- `datetime` покрывает все случаи использования `date`

## Важно: Стратегия выполнения

⚠️ **НЕ создавать новую миграцию!** 

Вместо этого:
1. Исправить целевую миграцию `2025_11_20_115359_create_paths_table.php` — убрать `'date'` из enum
2. Исправить все файлы кода, сидеры и тесты
3. Выполнить `php artisan migrate:refresh --seed` для пересоздания БД с обновлённой структурой

Это безопасно, так как обратная совместимость не требуется.

---

## Этап 1: Исправление целевой миграции

### 1.1. Обновить существующую миграцию создания таблицы paths

**Файл:** `database/migrations/2025_11_20_115359_create_paths_table.php`

**Действия:**
1. Удалить `'date'` из enum `data_type` в строке 24
2. Изменить: `['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref']` → `['string', 'text', 'int', 'float', 'bool', 'datetime', 'json', 'ref']`

**Изменение:**
```php
// Было (строка 24):
$table->enum('data_type', ['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref']);

// Станет:
$table->enum('data_type', ['string', 'text', 'int', 'float', 'bool', 'datetime', 'json', 'ref']);
```

---

## Этап 2: Изменения в коде

### 2.1. Константы и типы

#### `app/Domain/Blueprint/Validation/ValidationConstants.php`
- [ ] Удалить константу `DATA_TYPE_DATE`
- [ ] Обновить комментарии, убрав упоминания `date`

#### `app/Models/Path.php`
- [ ] Обновить PHPDoc для `$data_type`: убрать `date` из списка типов
- [ ] Изменить: `string|text|int|float|bool|date|datetime|json|ref` → `string|text|int|float|bool|datetime|json|ref`

### 2.2. Маппинг типов

#### `app/Domain/Blueprint/Validation/DataTypeMapper.php`
- [ ] Удалить `ValidationConstants::DATA_TYPE_DATE` из match в методе `toLaravelRule()`
- [ ] Оставить только `ValidationConstants::DATA_TYPE_DATETIME => 'date'`

**Было:**
```php
ValidationConstants::DATA_TYPE_DATE,
ValidationConstants::DATA_TYPE_DATETIME => 'date',
```

**Станет:**
```php
ValidationConstants::DATA_TYPE_DATETIME => 'date',
```

### 2.3. Индексация Entry

#### `app/Services/Entry/EntryIndexer.php`
- [ ] Удалить ветку `'date' => 'value_datetime'` из метода `getValueFieldForType()`
- [ ] Удалить ветку `'date' => ...` из метода `castValue()`
- [ ] Обновить PHPDoc метода `castValue()`: убрать `date` из списка типов
- [ ] Обновить комментарий в методе `castValue()`: убрать упоминание date-типа

**Изменения:**
- Строка 220: удалить `'date' => 'value_datetime',`
- Строка 244-246: удалить обработку `'date'`
- Строка 234: обновить комментарий

### 2.4. Запросы к данным

#### `app/Traits/HasDocumentData.php`
- [ ] Обновить SQL в методе `scopeOrderByPath()`: убрать `'date'` из условия
- [ ] Изменить: `WHEN p_sort.data_type IN ('date', 'datetime')` → `WHEN p_sort.data_type = 'datetime'`

**Строка 167:**
```php
// Было:
WHEN p_sort.data_type IN ('date', 'datetime') THEN dv_sort.value_datetime

// Станет:
WHEN p_sort.data_type = 'datetime' THEN dv_sort.value_datetime
```

### 2.5. Валидация запросов

#### `app/Http/Requests/Admin/Path/StorePathRequest.php`
- [ ] Удалить `'date'` из массива разрешённых значений `data_type`
- [ ] Изменить: `['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref']` → `['string', 'text', 'int', 'float', 'bool', 'datetime', 'json', 'ref']`

#### `app/Http/Requests/Admin/Path/Concerns/PathValidationRules.php`
- [ ] Удалить `'date'` из массива разрешённых значений `data_type` (строка 77)

### 2.6. Документация API

#### `app/Http/Controllers/Admin/V1/PathController.php`
- [ ] Обновить PHPDoc комментарии `@bodyParam data_type`: убрать `date` из списка значений
- [ ] Строки 88, 193: изменить `Values: string,text,int,float,bool,date,datetime,json,ref` → `Values: string,text,int,float,bool,datetime,json,ref`

### 2.7. Интерфейсы и документация

#### `app/Domain/Blueprint/Validation/BlueprintContentValidatorInterface.php`
- [ ] Обновить комментарий: убрать `date` из списка типов (строка 25)

#### `app/Domain/Blueprint/Validation/EntryValidationServiceInterface.php`
- [ ] Обновить комментарий: убрать `date` из списка типов (строка 26)

---

## Этап 3: Тесты и сидеры

### 3.1. Сидеры

#### `database/seeders/BlueprintsSeeder.php`
- [ ] Строка 351: заменить `'data_type' => 'date'` на `'data_type' => 'datetime'` (поле `birth_date`)
- [ ] Строка 402: заменить `'data_type' => 'date'` на `'data_type' => 'datetime'` (поле `founded_at`)

### 3.2. Тесты

#### `tests/Unit/Services/Entry/EntryIndexerTest.php`
- [ ] Строка 235: обновить название теста: `'индексация date-типа сохраняется в value_datetime с временем 00:00:00'` → `'индексация datetime-типа сохраняется в value_datetime'`
- [ ] Строка 243: заменить `'data_type' => 'date'` на `'data_type' => 'datetime'`
- [ ] Строка 260: обновить проверку времени — для datetime не обязательно 00:00:00 (можно оставить или убрать эту проверку)

#### `tests/Feature/EntryIndexingTest.php`
- [ ] Строка 332: заменить `'data_type' => 'date'` на `'data_type' => 'datetime'`
- [ ] Тест проверяет сортировку по `published_date` — убедиться, что работает с `datetime`

#### `tests/Integration/UltraComplexBlueprintSystemTest.php`
- [ ] Строка 299: заменить `'data_type' => 'date'` на `'data_type' => 'datetime'` (поле `birth_date` в Person blueprint)
- [ ] Строка 351: заменить `'data_type' => 'date'` на `'data_type' => 'datetime'` (поле `founded_at` в Organization blueprint)

#### `tests/Feature/Api/Admin/Blueprints/PathSchemasTest.php`
- [ ] Строка 480: обновить комментарий `// date` → `// datetime` (опционально)
- [ ] Строка 483: заменить `'data_type' => 'date'` на `'data_type' => 'datetime'` (поле `created_date`)
- [ ] Строка 533: обновить assertion: `$this->assertEquals('date', $createdDatePath->data_type)` → `$this->assertEquals('datetime', $createdDatePath->data_type)`

---

## Этап 4: Документация

### 4.1. Markdown документация

#### `docs/blueprint-validation-system.md`
- [ ] Строка 550: убрать `date` из списка типов
- [ ] Строка 860-861: удалить строку с `date` или обновить описание

---

## Этап 5: Проверка и тестирование

### 5.1. Проверочный список

- [ ] Исправить миграцию `create_paths_table.php` — убрать `'date'` из enum
- [ ] Исправить все файлы кода согласно плану
- [ ] Исправить сидеры — заменить `'date'` на `'datetime'`
- [ ] Исправить тесты — заменить `'date'` на `'datetime'`
- [ ] Выполнить `php artisan migrate:refresh --seed`
- [ ] Запустить тесты: `php artisan test`
- [ ] Проверить создание нового Path с типом `datetime` через API
- [ ] Проверить индексацию Entry с полем типа `datetime`
- [ ] Проверить сортировку по полю типа `datetime` через `orderByPath()`
- [ ] Проверить валидацию: попытка создать Path с `data_type = 'date'` должна вернуть ошибку валидации

### 5.2. Команды для выполнения

```bash
# 1. Исправить миграцию и сидеры (все изменения в коде)
# (внести изменения в файлы согласно плану)

# 2. Пересоздать БД с обновлённой миграцией и сидерами
php artisan migrate:refresh --seed

# 3. Запустить тесты
php artisan test

# 4. Сгенерировать документацию
composer scribe:gen
php artisan docs:generate
```

---

## Порядок выполнения

1. **Исправить целевую миграцию** — удалить `'date'` из enum в `create_paths_table.php`
2. **Исправить код** — удалить обработку `date` из всех мест
3. **Исправить сидеры** — заменить `'date'` на `'datetime'`
4. **Исправить тесты** — заменить `'date'` на `'datetime'`
5. **Пересоздать БД** — выполнить `php artisan migrate:refresh --seed`
6. **Обновить документацию** — убрать упоминания `date`

---

## Риски и предупреждения

⚠️ **Внимание:**
- После удаления `date` из enum, попытки создать Path с `data_type = 'date'` будут падать с ошибкой валидации
- Все изменения нужно внести до выполнения `migrate:refresh --seed`
- Сидеры должны использовать только `'datetime'` вместо `'date'`
- После `migrate:refresh` все данные будут удалены и пересозданы заново

---

## Файлы для изменения (итоговый список)

### Миграции
- `database/migrations/2025_11_20_115359_create_paths_table.php` (исправить enum, убрать `'date'`)

### Код приложения
- `app/Domain/Blueprint/Validation/ValidationConstants.php`
- `app/Domain/Blueprint/Validation/DataTypeMapper.php`
- `app/Models/Path.php`
- `app/Services/Entry/EntryIndexer.php`
- `app/Traits/HasDocumentData.php`
- `app/Http/Requests/Admin/Path/StorePathRequest.php`
- `app/Http/Requests/Admin/Path/Concerns/PathValidationRules.php`
- `app/Http/Controllers/Admin/V1/PathController.php`
- `app/Domain/Blueprint/Validation/BlueprintContentValidatorInterface.php`
- `app/Domain/Blueprint/Validation/EntryValidationServiceInterface.php`

### Тесты и сидеры
- `database/seeders/BlueprintsSeeder.php` (2 места: строки 351, 402)
- `tests/Unit/Services/Entry/EntryIndexerTest.php` (2 места: строка 235 - название теста, строка 243 - data_type)
- `tests/Feature/EntryIndexingTest.php` (1 место: строка 332 - data_type)
- `tests/Integration/UltraComplexBlueprintSystemTest.php` (2 места: строки 299, 351 - data_type)
- `tests/Feature/Api/Admin/Blueprints/PathSchemasTest.php` (3 места: строка 480 - комментарий, строка 483 - data_type, строка 533 - assertion)

### Документация
- `docs/blueprint-validation-system.md`

**Всего файлов: 14** (без создания новой миграции)

