# Blueprint System Quick Start

Краткое руководство по началу работы с Blueprint системой.

---

## Текущее состояние

✅ **Реализовано:**

-   Миграции БД для всех таблиц (`blueprints`, `paths`, `doc_values`, `doc_refs`, `blueprint_components`)
-   Модели Eloquent с отношениями и кэшированием
-   Observers для автоматической материализации/демaterialization paths
-   API контроллеры для CRUD операций с Blueprints, Paths, Components
-   Artisan команды для управления системой
-   HasDocumentData трейт для индексации Entry
-   Тестовые данные (3 Blueprints: 2 component + 1 full)

✅ **Проверено:**

-   Все существующие тесты проходят (1057 passed)
-   Базовая функциональность работает:
    -   Создание Blueprint'ов и Paths
    -   Композиция компонентов с материализацией
    -   Индексация Entry в `doc_values`
    -   Query scopes для поиска по индексам

---

## Что уже есть в БД

После миграций и сидеров у вас есть:

**Blueprint "SEO Fields" (component)**

-   `seo.metaTitle` (string, indexed)
-   `seo.metaDescription` (text)

**Blueprint "Author Info" (component)**

-   `author.name` (string, indexed)

**Blueprint "Article Full" (full)**

-   `content` (text, indexed)
-   `excerpt` (text)
-   **+ материализованные пути из компонентов:**
    -   `seo.metaTitle` (из SEO Fields)
    -   `seo.metaDescription` (из SEO Fields)
    -   `author.name` (из Author Info)

---

## Первые шаги

### 1. Проверить Blueprint'ы

```bash
php artisan tinker
```

```php
// Список всех Blueprints
\App\Models\Blueprint::all()->pluck('name', 'slug');

// Blueprint с компонентами
$bp = \App\Models\Blueprint::where('slug', 'article_full')->first();
echo $bp->name;
echo "Own Paths: " . $bp->ownPaths->count();
echo "All Paths (with materialized): " . $bp->getAllPaths()->count();

// Компоненты
$bp->components->each(function($c) {
    echo "{$c->name} (prefix: {$c->pivot->path_prefix})";
});
```

---

### 2. Создать Entry с индексацией

```php
$blueprint = \App\Models\Blueprint::where('slug', 'article_full')->first();

$entry = \App\Models\Entry::create([
    'post_type_id' => \App\Models\PostType::first()->id,
    'blueprint_id' => $blueprint->id,
    'title' => 'Test Article',
    'slug' => 'test-article',
    'status' => 'published',
    'published_at' => now(),
    'author_id' => \App\Models\User::first()->id,
    'data_json' => [
        'content' => 'This is article content...',
        'excerpt' => 'Short excerpt',
        'seo' => [
            'metaTitle' => 'SEO Title for Article',
            'metaDescription' => 'SEO description...',
        ],
        'author' => [
            'name' => 'John Doe',
        ],
    ],
]);

// Автоматически создаются записи в doc_values
echo "Indexed values: " . $entry->values()->count();

// Посмотреть индексы
$entry->values->each(function($v) {
    echo "{$v->path->full_path} = {$v->getValue()}";
});
```

---

### 3. Запросы по индексам

```php
// Найти Entry по SEO заголовку
$entries = \App\Models\Entry::wherePath('seo.metaTitle', '=', 'SEO Title for Article')->get();

// Найти Entry по содержанию
$entries = \App\Models\Entry::wherePathTyped('content', 'text', 'LIKE', '%article content%')->get();

// Найти Entry по имени автора
$entries = \App\Models\Entry::wherePath('author.name', '=', 'John Doe')->get();
```

---

### 4. Создать свой component через API

```bash
# Создать Blueprint компонент
curl -X POST http://localhost/api/v1/admin/blueprints \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "slug": "gallery_fields",
    "name": "Gallery Fields",
    "description": "Переиспользуемый компонент для галереи",
    "type": "component"
  }'
```

```bash
# Добавить Path в компонент
curl -X POST http://localhost/api/v1/admin/blueprints/BLUEPRINT_ID/paths \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "images",
    "full_path": "images",
    "data_type": "json",
    "cardinality": "many",
    "is_indexed": false
  }'
```

```bash
# Прикрепить компонент к full Blueprint
curl -X POST http://localhost/api/v1/admin/blueprints/FULL_BLUEPRINT_ID/components \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "component_id": COMPONENT_ID,
    "path_prefix": "gallery"
  }'
```

---

### 5. Миграция существующих Entry

Если у вас уже есть Entry в БД:

```bash
# Dry run (без изменений)
php artisan entries:migrate-to-blueprints --dry-run

# Реальная миграция
php artisan entries:migrate-to-blueprints

# Проверка
php artisan entries:validate-migration
```

**Что происходит:**

1. Для каждого PostType создается default Blueprint (type=full)
2. Все Entry связываются с default Blueprint
3. Запускается реиндексация Entry (если есть indexed Paths)

---

### 6. Реиндексация Entry

После изменений в Blueprint (добавление/удаление Paths, изменение `is_indexed`):

```bash
# Реиндексация всех Entry
php artisan entries:reindex

# Реиндексация конкретного PostType
php artisan entries:reindex --post-type=article

# Реиндексация конкретного Blueprint
php artisan entries:reindex --blueprint=article_full

# Асинхронно (через очередь)
php artisan entries:reindex --queue
```

---

### 7. Экспорт/импорт Blueprint

```bash
# Экспорт схемы Blueprint в JSON
php artisan blueprint:export article_full
php artisan blueprint:export article_full --output=/path/to/blueprint.json

# Импорт схемы
php artisan blueprint:import /path/to/blueprint.json --post-type=article
```

Полезно для:

-   Переноса схем между окружениями (dev → staging → prod)
-   Версионирования схем
-   Резервного копирования

---

### 8. Диагностика Blueprint

```bash
php artisan blueprint:diagnose article_full
```

Показывает:

-   Количество собственных и материализованных Paths
-   Количество компонентов
-   Количество Entry
-   Распределение по типам полей (`data_type`)

---

## Типичные сценарии

### Сценарий 1: Добавить новое поле в компонент

**Проблема:** Нужно добавить поле `ogImage` в SEO компонент.

**Решение:**

1. Добавить Path в компонент:

```bash
POST /api/v1/admin/blueprints/{seo_component_id}/paths
{
  "name": "ogImage",
  "full_path": "ogImage",
  "data_type": "string",
  "is_indexed": true
}
```

2. PathObserver автоматически:

    - Материализует новый Path во всех full Blueprint'ах, использующих этот компонент
    - Запускает реиндексацию Entry (асинхронно)

3. Новое поле сразу доступно:

```php
$entry->data_json = [
    'seo' => [
        'metaTitle' => '...',
        'metaDescription' => '...',
        'ogImage' => 'https://example.com/image.jpg', // новое поле!
    ],
];
$entry->save(); // автоматически индексируется
```

---

### Сценарий 2: Создать переиспользуемый компонент для адреса

```php
// 1. Создать component Blueprint
$address = Blueprint::create([
    'slug' => 'address_fields',
    'name' => 'Address Fields',
    'type' => 'component',
]);

// 2. Добавить Paths
$paths = [
    ['name' => 'street', 'full_path' => 'street', 'data_type' => 'string', 'is_indexed' => true],
    ['name' => 'city', 'full_path' => 'city', 'data_type' => 'string', 'is_indexed' => true],
    ['name' => 'zipCode', 'full_path' => 'zipCode', 'data_type' => 'string', 'is_indexed' => true],
    ['name' => 'country', 'full_path' => 'country', 'data_type' => 'string', 'is_indexed' => true],
];

foreach ($paths as $pathData) {
    $address->ownPaths()->create($pathData);
}

// 3. Прикрепить к разным Blueprint'ам с разными префиксами
$storeBlueprint->components()->attach($address->id, ['path_prefix' => 'shipping_address']);
$userBlueprint->components()->attach($address->id, ['path_prefix' => 'billing_address']);

// 4. Теперь можно использовать:
$store->data_json = [
    'shipping_address' => [
        'street' => '123 Main St',
        'city' => 'New York',
        'zipCode' => '10001',
        'country' => 'USA',
    ],
];

$user->data_json = [
    'billing_address' => [
        'street' => '456 Oak Ave',
        'city' => 'Los Angeles',
        'zipCode' => '90001',
        'country' => 'USA',
    ],
];

// 5. Запросы работают:
Entry::wherePath('shipping_address.city', '=', 'New York')->get();
Entry::wherePath('billing_address.city', '=', 'Los Angeles')->get();
```

---

### Сценарий 3: Поиск Entry по ссылке

```php
// 1. Создать Path типа 'ref'
$blueprint->ownPaths()->create([
    'name' => 'relatedArticles',
    'full_path' => 'relatedArticles',
    'data_type' => 'ref',
    'cardinality' => 'many',
    'is_indexed' => true,
    'ref_target_type' => 'article', // опционально: указать тип
]);

// 2. Создать Entry со ссылками
$entry->data_json = [
    'relatedArticles' => [10, 15, 20], // ID других Entry
];
$entry->save(); // автоматически создаются записи в doc_refs

// 3. Найти Entry, которые ссылаются на Entry #15
$entries = Entry::whereRef('relatedArticles', 15)->get();
```

---

## Troubleshooting

### Проблема: Entry не индексируются

**Причина:** `blueprint_id` отсутствует или Path не имеет `is_indexed=true`.

**Решение:**

```bash
# Проверить Entry
php artisan tinker
>>> \App\Models\Entry::whereNull('blueprint_id')->count()

# Мигрировать Entry к Blueprints
php artisan entries:migrate-to-blueprints

# Проверить Paths
>>> \App\Models\Path::where('is_indexed', true)->count()

# Обновить Path
$path = \App\Models\Path::where('full_path', 'content')->first();
$path->update(['is_indexed' => true]);

# Реиндексировать Entry
php artisan entries:reindex
```

---

### Проблема: Кэш Blueprint не обновляется

**Причина:** `getAllPaths()` кэширует результат на 1 час.

**Решение:**

```php
// Вручную очистить кэш
$blueprint->invalidatePathsCache();

// Или через Redis/Memcached
\Illuminate\Support\Facades\Cache::forget("blueprint:{$blueprint->id}:all_paths");
```

Или просто подождите час 😊

---

### Проблема: Конфликт имен при attach компонента

**Причина:** `path_prefix` создает конфликт с существующими Paths.

**Решение:** Используйте другой `path_prefix`:

```php
// ❌ Плохо: конфликт с существующим Path "seo"
$blueprint->components()->attach($seo->id, ['path_prefix' => 'seo']);

// ✅ Хорошо: уникальный префикс
$blueprint->components()->attach($seo->id, ['path_prefix' => 'meta']);
// Теперь поля будут: meta.metaTitle, meta.metaDescription
```

---

## Дополнительные ресурсы

-   **Архитектурный план:** `docs/document_path_index_laravel_plan_v2_fixed.md`
-   **План реализации:** `docs/implementation_plan_blueprint_system.md`
-   **API Guide:** `docs/blueprint_api_guide.md`
-   **Scribe API Docs:** `docs/generated/api-docs/index.html`
-   **Навигация:** `docs/generated/README.md`

---

## Roadmap

**Следующие шаги:**

1. Создать тесты для новой функциональности (МОДУЛЬ 7)
2. Оптимизировать batch insert для `doc_values`/`doc_refs` (МОДУЛЬ 9)
3. Добавить UI для визуального редактирования Blueprint схем
4. Реализовать валидацию на основе `validation_rules` в Path
5. Генерация API ресурсов из Blueprint

---

## Поддержка

Если что-то не работает:

1. Проверьте логи: `storage/logs/laravel.log`
2. Запустите диагностику: `php artisan blueprint:diagnose <slug>`
3. Проверьте валидацию миграции: `php artisan entries:validate-migration`
4. Проверьте тесты: `php artisan test`

**Контакты:** Обращайтесь к документации в `docs/` или к архитектору проекта.
