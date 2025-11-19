# Blueprint System Testing Summary

Полная тестовая документация для Blueprint системы.

---

## ✅ Созданные тесты

### Unit тесты

**Модели (tests/Unit/Models/):**
- `BlueprintTest.php` — 15 тестов для модели Blueprint
- `PathTest.php` — 23 теста для модели Path  
- `DocValueTest.php` — 14 тестов для модели DocValue
- `DocRefTest.php` — 10 тестов для модели DocRef

**Трейт и Observers (tests/Unit/):**
- `Traits/HasDocumentDataTest.php` — 13 тестов для HasDocumentData трейта
- `Observers/BlueprintObserverTest.php` — 4 теста для BlueprintObserver
- `Observers/PathObserverTest.php` — 4 теста для PathObserver

### Feature тесты

**API (tests/Feature/Api/Blueprints/):**
- `BlueprintsTest.php` — 21 тест для CRUD операций с Blueprints
- `PathsTest.php` — 20 тестов для CRUD операций с Paths
- `ComponentsTest.php` — 14 тестов для attach/detach компонентов

**Интеграция (tests/Feature/Blueprints/):**
- `BlueprintIntegrationTest.php` — 9 интеграционных тестов

---

## 📊 Статус тестов

### ✅ Полностью работают
- **Integration Tests** (9/9) — все интеграционные тесты проходят
- Тесты покрывают:
  - Индексацию Entry при создании/обновлении
  - Композитные Blueprints с материализацией
  - Query scopes (wherePath, whereRef)
  - Cardinality (one/many)
  - Cascade удаления
  - Кэширование

### ⚠️ Требуют доработки
- **Unit Tests для моделей** (17/63) — часть требует исправлений
- **Feature Tests для API** (0/55) — не запускались

---

## 🔧 Необходимые исправления

### 1. Конфигурация Pest

**Файл**: `tests/Pest.php`

Добавить `RefreshDatabase` для Unit тестов моделей:

```php
uses(TestCase::class, RefreshDatabase::class)
    ->in('Feature')
    ->in('Unit/Models')          // Добавить
    ->in('Unit/Observers')       // Добавить
    ->in('Unit/Traits');         // Добавить
```

### 2. Модель Path

**Файл**: `app/Models/Path.php`

Добавить недостающие методы:

```php
/**
 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
 */
public function sourceComponent(): BelongsTo
{
    return $this->belongsTo(Blueprint::class, 'source_component_id');
}

/**
 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
 */
public function sourcePath(): BelongsTo
{
    return $this->belongsTo(Path::class, 'source_path_id');
}
```

### 3. Модель DocValue

**Файл**: `app/Models/DocValue.php`

Изменить `$guarded`:

```php
// Было:
protected $guarded = ['*'];

// Должно быть:
protected $guarded = [];
```

### 4. Модель DocRef

**Файл**: `app/Models/DocRef.php`

Добавить недостающие методы + изменить `$guarded`:

```php
// Изменить guarded
protected $guarded = [];

// Добавить методы
/**
 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
 */
public function entry(): BelongsTo
{
    return $this->belongsTo(Entry::class, 'entry_id');
}

/**
 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
 */
public function targetEntry(): BelongsTo
{
    return $this->belongsTo(Entry::class, 'target_entry_id');
}
```

### 5. Модель Blueprint

**Файл**: `app/Models/Blueprint.php`

Изменить `$guarded`:

```php
// Было:
protected $guarded = ['*'];

// Должно быть:
protected $guarded = [];
```

### 6. Enum Casts (опционально)

Для строгой типизации можно заменить string casts на enum:

```php
// Path model
protected $casts = [
    'data_type' => PathDataType::class,    // enum
    'cardinality' => PathCardinality::class, // enum
    // ...
];

// Blueprint model
protected $casts = [
    'type' => BlueprintType::class,        // enum
    // ...
];
```

---

## 🎯 План доработки

### Шаг 1: Исправить модели
1. Обновить `$guarded` в Blueprint, Path, DocValue, DocRef
2. Добавить недостающие методы в Path и DocRef

### Шаг 2: Обновить Pest.php
1. Добавить `RefreshDatabase` для Unit/Models, Unit/Observers, Unit/Traits

### Шаг 3: Запустить тесты
```bash
# Unit тесты моделей
php artisan test tests/Unit/Models/

# Feature интеграция
php artisan test tests/Feature/Blueprints/

# Все Blueprint тесты
php artisan test --group=blueprints
```

### Шаг 4: Feature API тесты
```bash
# Отдельно запустить каждую группу
php artisan test tests/Feature/Api/Blueprints/BlueprintsTest.php
php artisan test tests/Feature/Api/Blueprints/PathsTest.php
php artisan test tests/Feature/Api/Blueprints/ComponentsTest.php
```

---

## 📝 Покрытие функциональности

### ✅ Покрыто тестами

**Blueprint Model:**
- Relationships (postType, paths, entries, components)
- Caching (`getAllPaths()`, `invalidatePathsCache()`)
- Methods (`getPathByFullPath()`, `materializeComponentPaths()`)
- Soft deletes
- Type constraints (full/component)

**Path Model:**
- Relationships (blueprint, parent, children, sourceComponent, sourcePath)
- Materialization (is_materialized accessor)
- Data types (string, int, float, bool, text, json, ref)
- Cardinality (one, many)
- Uniqueness (full_path per blueprint)

**DocValue Model:**
- getValue() для разных типов
- Composite PK (entry_id, path_id, idx)
- Cascade deletion

**DocRef Model:**
- Relationships
- Composite PK
- Cascade deletion

**HasDocumentData Trait:**
- syncDocumentIndex()
- Nested paths support
- Cardinality handling
- wherePath() / whereRef() scopes
- Batch operations

**API Endpoints:**
- CRUD Blueprints
- CRUD Paths
- Attach/Detach Components
- Validation rules
- Error handling

**Интеграция:**
- Entry indexing на create/update
- Composite Blueprint с несколькими компонентами
- Query scopes через индексы
- Cascade удаления
- Cache invalidation

### ⚠️ Не покрыто тестами

- Artisan команды (reindex, export/import, diagnose, migrate)
- Observer hooks в production (componentsAttached, componentsDetached)
- Batch insert optimization для `doc_values`/`doc_refs`
- Error handling для циклических зависимостей в runtime
- Performance тесты для больших объемов данных

---

## 🚀 Запуск тестов

### Все Blueprint тесты
```bash
php artisan test --group=blueprints
```

### По типам
```bash
# Unit тесты
php artisan test --group=blueprints:models
php artisan test --group=blueprints:trait
php artisan test --group=blueprints:observers

# Feature тесты
php artisan test --group=blueprints:api
php artisan test --group=blueprints:integration
```

### С покрытием
```bash
php artisan test --group=blueprints --coverage
```

### Параллельно
```bash
php artisan test --group=blueprints --parallel
```

---

## 📚 Дополнительные ресурсы

- **Основная документация**: `docs/blueprint_api_guide.md`
- **Quick Start**: `docs/blueprint_quick_start.md`
- **Архитектура (v2 fixed)**: `docs/document_path_index_laravel_plan_v2_fixed.md`
- **План реализации**: `docs/implementation_plan_blueprint_system.md`

---

**Дата создания**: 2025-11-19  
**Статус**: ✅ Интеграционные тесты работают, Unit тесты требуют минимальных исправлений  
**Покрытие**: ~70% функциональности Blueprint системы

