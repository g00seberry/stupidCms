---
owner: "@backend-team"
system_of_record: "narrative"
review_cycle_days: 60
last_reviewed: 2025-11-08
related_code:
  - "app/Models/EntrySlug.php"
  - "app/Models/Redirect.php"
  - "app/Support/Slug/*.php"
  - "app/Support/EntrySlug/*.php"
---

# Slugs & 301 Redirects

В stupidCms URL (slugs) — это first-class сущность с историей, автоматическими 301-редиректами и защитой от конфликтов.

## Концепция

### Почему slug — это больше, чем строка?

В типичной CMS slug — это поле в таблице `entries`:

```sql
entries: id, title, slug, content
```

**Проблема**: При изменении slug старый URL **исчезает**, ломая внешние ссылки и SEO.

### Решение: EntrySlug

stupidCms хранит **историю всех slugs** в отдельной таблице:

```sql
entry_slugs: entry_id, slug, is_current, parent_slug, created_at
```

**Преимущества**:
- Автоматический 301-редирект со старого URL на новый
- История изменений URL
- Поддержка иерархии (parent/child)
- Защита от конфликтов с системными маршрутами

## Модель данных

### EntrySlug

**Таблица**: `entry_slugs`

```php
EntrySlug {
  entry_id: bigint (FK → entries.id, часть PK)
  slug: string (часть PK)
  is_current: boolean
  parent_slug: ?string
  created_at: datetime
}
```

**Primary Key**: composite `(entry_id, slug)`

**Индексы**:
- `slug` — для резолва URL
- `is_current` — для поиска текущего slug

**Файл**: `app/Models/EntrySlug.php`

---

### Redirect

**Назначение**: Ручные 301-редиректы (не связанные с entries).

**Таблица**: `redirects`

```php
Redirect {
  id: bigint (PK)
  from_path: string (unique)    // '/old-url'
  to_path: string               // '/new-url' or 'https://external.com'
  status_code: int (default: 301)
  created_at: datetime
  updated_at: datetime
}
```

**Файл**: `app/Models/Redirect.php`

## Жизненный цикл slug

### 1. Создание Entry

```php
Entry::create([
    'title' => 'Laravel 12 Released',
    'slug' => 'laravel-12-released',
    // ...
]);
```

**Что происходит** (через `EntryObserver`):

```sql
INSERT INTO entry_slugs (entry_id, slug, is_current, created_at)
VALUES (1, 'laravel-12-released', true, NOW());
```

---

### 2. Изменение slug

```php
$entry->update(['slug' => 'laravel-12-new-features']);
```

**Что происходит**:

```sql
-- Шаг 1: Старый slug → is_current = false
UPDATE entry_slugs
SET is_current = false
WHERE entry_id = 1 AND slug = 'laravel-12-released';

-- Шаг 2: Новый slug → is_current = true
INSERT INTO entry_slugs (entry_id, slug, is_current, created_at)
VALUES (1, 'laravel-12-new-features', true, NOW());
```

---

### 3. Резолв URL (301-редирект)

Пользователь заходит на `/articles/laravel-12-released`:

1. **Поиск entry**:
   ```php
   $entrySlug = EntrySlug::where('slug', 'laravel-12-released')->first();
   $entry = $entrySlug->entry;
   ```

2. **Проверка is_current**:
   ```php
   if (!$entrySlug->is_current) {
       // Найти текущий slug
       $currentSlug = $entry->slugs()->where('is_current', true)->first();
       return redirect($currentSlug->slug, 301);
   }
   ```

3. **Редирект**:
   ```
   HTTP/1.1 301 Moved Permanently
   Location: /articles/laravel-12-new-features
   ```

**Файл**: `app/Support/Slug/SlugResolver.php` _(примерно)_

## Иерархические slugs

### Parent-Child структура

Для PostType `page` с `hierarchical: true`:

```
Page: "О компании" (slug: about)
  └─ Page: "Наша команда" (slug: team, parent_slug: about)
```

**entry_slugs**:
```
entry_id | slug | is_current | parent_slug
---------+------+------------+-------------
1        | about| true       | null
2        | team | true       | about
```

**URL**: `/about/team`

### Изменение parent

```php
$teamPage->update(['parent_slug' => 'company']);
```

**Результат**:
- Старый: `/about/team` → `is_current = false`
- Новый: `/company/team` → `is_current = true`
- 301-редирект: `/about/team` → `/company/team`

## Reserved Routes (защита от конфликтов)

### Что это?

Системные URL, которые **нельзя использовать** для пользовательских slugs:

- `/api/*`
- `/admin/*`
- `/auth/*`
- Кастомные из `reserved_routes` таблицы

### Проверка при валидации

```php
// app/Rules/ReservedSlug.php

public function passes($attribute, $value): bool
{
    $reserved = ReservedRoute::all()->pluck('pattern');
    
    foreach ($reserved as $pattern) {
        if (fnmatch($pattern, "/{$value}")) {
            return false;
        }
    }
    
    return true;
}
```

**Пример**:
```php
Entry::create(['slug' => 'api/test']);  // ❌ ValidationException
Entry::create(['slug' => 'my-article']); // ✅ OK
```

### Добавление reserved route

```php
ReservedRoute::create([
    'pattern' => '/dashboard/*',
    'description' => 'Admin dashboard routes',
]);
```

## Ручные редиректы (Redirect)

Для случаев, когда нужен редирект **не связанный с entry**:

```php
Redirect::create([
    'from_path' => '/old-blog',
    'to_path' => '/articles',
    'status_code' => 301,
]);
```

**Middleware** проверяет редиректы **перед** роутингом:

```php
// app/Http/Middleware/HandleRedirects.php

public function handle($request, $next)
{
    $redirect = Redirect::where('from_path', $request->path())->first();
    
    if ($redirect) {
        return redirect($redirect->to_path, $redirect->status_code);
    }
    
    return $next($request);
}
```

**Порядок проверок**:
1. **HandleRedirects** → `redirects` таблица
2. **SlugResolver** → `entry_slugs` таблица
3. Laravel Router → роуты из `routes/*`

## Генерация slug

### Автоматическая генерация

Если slug не указан, генерируется из `title`:

```php
// app/Support/Slug/SlugGenerator.php

public function generate(string $title, ?int $maxLength = 255): string
{
    $slug = Str::slug($title, '-', 'ru');  // транслитерация
    $slug = Str::limit($slug, $maxLength, '');
    
    // Проверка уникальности
    $counter = 1;
    $original = $slug;
    while (EntrySlug::where('slug', $slug)->exists()) {
        $slug = "{$original}-{$counter}";
        $counter++;
    }
    
    return $slug;
}
```

**Пример**:
```php
Entry::create(['title' => 'Привет мир']);
// slug: 'privet-mir'

Entry::create(['title' => 'Привет мир']);  // дубликат
// slug: 'privet-mir-2'
```

### Кастомная генерация

Для SEO-оптимизации:

```php
$slug = SlugGenerator::generate($title, maxLength: 50);
// Ограничивает длину для коротких, запоминающихся URL
```

## URL Prefix по PostType

В `PostType.options_json`:

```json
{
  "slugs": {
    "prefix": "articles"
  }
}
```

**Результат**: Entry имеет slug `my-post`, но URL: `/articles/my-post`

**Резолв**:
```php
$postTypeSlug = 'articles';
$entrySlug = 'my-post';

$entry = Entry::ofType($postTypeSlug)
    ->whereHas('slugs', fn($q) => $q->where('slug', $entrySlug)->where('is_current', true))
    ->first();
```

**Файл**: `routes/web_content.php`

```php
Route::get('/{postTypeSlug}/{slug}', [EntryController::class, 'show']);
Route::get('/{slug}', [PageController::class, 'show']); // для page без префикса
```

## События

### EntrySlugChanged

Триггерится при изменении slug entry:

```php
// app/Events/EntrySlugChanged.php

class EntrySlugChanged
{
    public Entry $entry;
    public string $oldSlug;
    public string $newSlug;
}
```

**Использование** (например, для инвалидации кэша):

```php
// app/Listeners/InvalidateEntryCache.php

public function handle(EntrySlugChanged $event): void
{
    Cache::forget("entry:{$event->oldSlug}");
    Cache::forget("entry:{$event->newSlug}");
}
```

## API

### Изменение slug entry

**Endpoint**: `PUT /api/admin/entries/{id}`

**Request**:
```json
{
  "slug": "new-slug"
}
```

**Response**:
```json
{
  "data": {
    "id": 1,
    "slug": "new-slug",
    "old_slugs": ["old-slug-1", "old-slug-2"]
  }
}
```

### Получение истории slugs

**Endpoint**: `GET /api/admin/entries/{id}/slugs`

**Response**:
```json
{
  "data": [
    {
      "slug": "new-slug",
      "is_current": true,
      "created_at": "2025-11-08T12:00:00Z"
    },
    {
      "slug": "old-slug",
      "is_current": false,
      "created_at": "2025-11-01T10:00:00Z"
    }
  ]
}
```

## Best Practices

### ✅ DO

- Позволяйте пользователям редактировать slugs в админке
- Генерируйте короткие, читаемые slugs
- Используйте транслитерацию для кириллицы
- Проверяйте уникальность slugs
- Храните историю для 301-редиректов

### ❌ DON'T

- Не удаляйте старые slugs из `entry_slugs`
- Не используйте slug как единственный идентификатор (всегда есть `id`)
- Не позволяйте конфликты с reserved routes
- Не создавайте слишком длинные slugs (макс 100-150 символов)

## Связанные страницы

- [Entries](entries.md) — работа с записями
- [Routes Reference](../30-reference/routes.md) — автосгенерированный список роутов
- [How-to: Работа со слагами](../20-how-to/slugs-management.md)

---

> 💡 **SEO Tip**: 301-редиректы сохраняют Page Rank. stupidCms делает это автоматически!

