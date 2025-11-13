---
owner: "@backend-team"
system_of_record: "narrative"
review_cycle_days: 60
last_reviewed: 2025-11-08
related_code:
  - "app/Models/Taxonomy.php"
  - "app/Models/Term.php"
  - "app/Models/TermTree.php"
---

# Taxonomy & Terms

**Taxonomy** — это способ категоризации контента через **Terms** (термины). Например: категории, теги, регионы, авторы.

## Концепция

### Зачем нужна таксономия?

Без таксономии:
```php
Entry { categories: 'Tech, Laravel, PHP' }  // ❌ строка
```

С таксономией:
```php
Entry → belongsToMany(Term)
Term { id: 1, taxonomy_id: 1, name: 'Laravel' }
Term { id: 2, taxonomy_id: 1, name: 'PHP' }
```

**Преимущества**:
- Структурированные данные
- Переименование термина → обновляется везде
- Поиск по термину
- Иерархия (например, "Технологии" → "Laravel" → "Eloquent")

## Модель данных

### Taxonomy

**Назначение**: Группа терминов (например, "Категории статей", "Теги").

**Таблица**: `taxonomies`

```php
Taxonomy {
  id: bigint (PK)
  slug: string (unique)     // 'categories', 'tags'
  name: string              // 'Категории', 'Теги'
  hierarchical: boolean     // поддержка parent-child
  created_at: datetime
  updated_at: datetime
}
```

**Связи**:
- `hasMany(Term)` — термины

**Файл**: `app/Models/Taxonomy.php`

---

### Term

**Назначение**: Конкретная категория, тег, регион (элемент таксономии).

**Таблица**: `terms`

```php
Term {
  id: bigint (PK)
  taxonomy_id: bigint (FK → taxonomies.id)
  slug: string (indexed)
  name: string
  description: ?text
  created_at: datetime
  updated_at: datetime
}
```

**Связи**:
- `belongsTo(Taxonomy)`
- `belongsToMany(Entry)` via `entry_term`
- `hasMany(TermTree, 'term_id')` — дочерние узлы
- `hasMany(TermTree, 'parent_id')` — родительские узлы

**Файл**: `app/Models/Term.php`

---

### TermTree

**Назначение**: Иерархия терминов (для `hierarchical = true`).

**Таблица**: `term_tree`

```php
TermTree {
  term_id: bigint (FK → terms.id, часть PK)
  parent_id: bigint (FK → terms.id, часть PK)
  level: int                  // глубина вложенности
  path: string                // '1/3/5' (полный путь)
}
```

**Primary Key**: composite `(term_id, parent_id)`

**Файл**: `app/Models/TermTree.php`

## Примеры таксономий

### 1. Categories (иерархические)

```php
Taxonomy::create([
    'slug' => 'categories',
    'name' => 'Категории',
    'hierarchical' => true,
]);

Term::create([
    'taxonomy_id' => 1,
    'slug' => 'tech',
    'name' => 'Технологии',
]);

Term::create([
    'taxonomy_id' => 1,
    'slug' => 'laravel',
    'name' => 'Laravel',
    'parent_id' => 1,  // child of "Технологии"
]);
```

**Иерархия**:
```
Технологии (id: 1)
  ├─ Laravel (id: 2)
  │   └─ Eloquent (id: 3)
  └─ PHP (id: 4)
      └─ PHP 8 (id: 5)
```

**term_tree**:
```
term_id | parent_id | level | path
--------+-----------+-------+-------
2       | 1         | 1     | 1/2
3       | 2         | 2     | 1/2/3
4       | 1         | 1     | 1/4
5       | 4         | 2     | 1/4/5
```

---

### 2. Tags (плоские)

```php
Taxonomy::create([
    'slug' => 'tags',
    'name' => 'Теги',
    'hierarchical' => false,
]);

Term::create(['taxonomy_id' => 2, 'slug' => 'featured', 'name' => 'Избранное']);
Term::create(['taxonomy_id' => 2, 'slug' => 'beginner', 'name' => 'Для новичков']);
```

**Структура**:
```
Теги
├─ Избранное (id: 10)
├─ Для новичков (id: 11)
└─ Туториал (id: 12)
```

(Нет `term_tree` записей, т.к. `hierarchical = false`)

## Связь Entry ↔ Terms

### Таблица entry_term

```sql
entry_id | term_id
---------+--------
1        | 2        // Entry#1 → Laravel
1        | 10       // Entry#1 → Избранное
2        | 4        // Entry#2 → PHP
```

### Привязка терминов

```php
$entry = Entry::find(1);

// Attach (добавить)
$entry->terms()->attach([2, 10]);

// Detach (удалить)
$entry->terms()->detach([10]);

// Sync (заменить все)
$entry->terms()->sync([2, 4]);
```

### Получение entries по термину

```php
$term = Term::where('slug', 'laravel')->first();
$entries = $term->entries()->published()->paginate(20);
```

### Фильтрация entries в API

**Endpoint**: `GET /api/entries?term_id=2`

```php
Entry::published()
    ->when($request->term_id, function ($q, $termId) {
        $q->whereHas('terms', fn($qq) => $qq->where('terms.id', $termId));
    })
    ->paginate(20);
```

## Иерархия терминов

### Получение потомков

```php
$parent = Term::find(1); // "Технологии"

$children = Term::whereHas('tree', function ($q) use ($parent) {
    $q->where('parent_id', $parent->id);
})->get();

// Или если есть связь:
$children = $parent->children; // через hasMany(TermTree, 'parent_id')
```

### Получение всех предков

```php
$term = Term::find(3); // "Eloquent"

// path: '1/2/3'
$ancestorIds = explode('/', $term->tree->path); // [1, 2, 3]
$ancestors = Term::findMany($ancestorIds);

// Result: [Технологии, Laravel, Eloquent]
```

### Breadcrumb

```php
function getBreadcrumb(Term $term): array
{
    $path = $term->tree->path ?? $term->id;
    $ids = explode('/', $path);
    return Term::findMany($ids)->pluck('name')->toArray();
}

getBreadcrumb($term);
// ['Технологии', 'Laravel', 'Eloquent']
```

## API

### Получение таксономий

**Endpoint**: `GET /api/taxonomies`

**Response**:
```json
{
  "data": [
    {
      "id": 1,
      "slug": "categories",
      "name": "Категории",
      "hierarchical": true
    },
    {
      "id": 2,
      "slug": "tags",
      "name": "Теги",
      "hierarchical": false
    }
  ]
}
```

---

### Получение терминов таксономии

**Endpoint**: `GET /api/taxonomies/{slug}/terms`

**Response** (для `categories`):
```json
{
  "data": [
    {
      "id": 1,
      "slug": "tech",
      "name": "Технологии",
      "parent_id": null,
      "children": [
        {
          "id": 2,
          "slug": "laravel",
          "name": "Laravel",
          "parent_id": 1
        }
      ]
    }
  ]
}
```

---

### Создание термина

**Endpoint**: `POST /api/v1/admin/taxonomies/{taxonomy}/terms`

**Request**:
```json
{
  "name": "Vue.js",
  "slug": "vue",
  "parent_id": 1,
  "meta_json": {}
}
```

**Response**: `201 Created`

> ⚠️ `parent_id` доступен только для иерархических таксономий (`hierarchical = true`). При указании `parent_id` автоматически создаются записи в `term_tree` (Closure Table).

---

### Обновление термина

**Endpoint**: `PUT /api/v1/admin/terms/{id}`

**Request**:
```json
{
  "name": "Vue.js 3",
  "parent_id": 1
}
```

> ⚠️ При изменении `parent_id` обновляется `term_tree` (Closure Table). Проверяется, что родитель принадлежит той же таксономии и не создаётся циклическая зависимость.

---

### Получение дерева терминов

**Endpoint**: `GET /api/v1/admin/taxonomies/{taxonomy}/terms/tree`

**Response**:
```json
{
  "data": [
    {
      "id": 1,
      "name": "Технологии",
      "slug": "tech",
      "parent_id": null,
      "children": [
        {
          "id": 2,
          "name": "Laravel",
          "slug": "laravel",
          "parent_id": 1,
          "children": []
        }
      ]
    }
  ]
}
```

> Для неиерархических таксономий возвращается плоский список терминов.

## Валидация

### Уникальность slug в рамках таксономии

```php
// app/Http/Requests/CreateTermRequest.php

public function rules(): array
{
    return [
        'taxonomy_id' => 'required|exists:taxonomies,id',
        'slug' => [
            'required',
            'string',
            Rule::unique('terms')->where(function ($q) {
                return $q->where('taxonomy_id', $this->taxonomy_id);
            }),
        ],
        'name' => 'required|string|max:255',
    ];
}
```

### Проверка parent в той же таксономии

```php
if ($request->parent_id) {
    $parent = Term::find($request->parent_id);
    
    if ($parent->taxonomy_id !== $request->taxonomy_id) {
        throw ValidationException::withMessages([
            'parent_id' => 'Parent term must belong to the same taxonomy',
        ]);
    }
}
```

## Использование в PostType

В `PostType.options_json`:

```json
{
  "taxonomies": ["categories", "tags"]
}
```

Означает, что entries этого типа могут иметь термины из `categories` и `tags`.

**Валидация** при привязке терминов к entry:

```php
$allowedTaxonomies = $entry->postType->options_json['taxonomies'] ?? [];
$requestedTerms = Term::findMany($request->term_ids);

foreach ($requestedTerms as $term) {
    if (!in_array($term->taxonomy->slug, $allowedTaxonomies)) {
        throw ValidationException::withMessages([
            'term_ids' => "Taxonomy '{$term->taxonomy->slug}' not allowed",
        ]);
    }
}
```

## Встроенные таксономии

При сиде создаются:

| Slug | Name | Hierarchical |
|------|------|--------------|
| `categories` | Категории | true |
| `tags` | Теги | false |

См. `database/seeders/PostTypesTaxonomiesSeeder.php`

## Best Practices

### ✅ DO

- Используйте `slug` для идентификации терминов в URL/API
- Для SEO-страниц категорий генерируйте URL вида `/categories/{slug}`
- Кэшируйте дерево терминов (оно редко меняется)
- Eager load `taxonomy` при выборке terms: `Term::with('taxonomy')->get()`

### ❌ DON'T

- Не делайте слишком глубокую иерархию (макс 3-4 уровня)
- Не привязывайте термины к entry напрямую — используйте `entry_term`
- Не храните иерархию только в `parent_id` — используйте `term_tree` для быстрого доступа

## Производительность

### Closure Table для иерархии

`term_tree` — это реализация Closure Table паттерна:
- Быстрое получение всех потомков (`WHERE path LIKE '1/%'`)
- Быстрое получение предков (`WHERE term_id IN (...)`)
- Без рекурсивных запросов

### Кэширование

```php
$categoriesTree = Cache::remember('categories_tree', 3600, function () {
    return Term::where('taxonomy_id', 1)
        ->with('children')
        ->whereNull('parent_id')
        ->get();
});
```

## Связанные страницы

- [Entries](entries.md) — привязка терминов к записям
- [Post Types](post-types.md) — настройка таксономий для типов
- Scribe API Reference (`../_generated/api-docs/index.html`) — endpoints

---

> 💡 **Tip**: Для больших древовидных структур (например, географические регионы) рассмотрите использование пакетов типа `kalnoy/nestedset`.

