# Контроллер публичной страницы (Задача 31)

## Обзор

Реализован `PageController@show` для отдачи публичных страниц по slug. Контроллер ищет Entry по текущему slug и разрешает только опубликованные записи.

**Ключевые особенности:**

-   Использует текущий slug из таблицы `entries` (не историю из `entry_slugs`)
-   Разрешает только опубликованные записи: `status='published'` и `published_at <= now()`
-   Возвращает 404 для черновиков, будущих публикаций и отсутствующих slug'ов
-   Индекс по `slug` для оптимизации производительности

## Основные компоненты

### 1. PageController

**Файл:** `app/Http/Controllers/PageController.php`

Контроллер для отображения публичных страниц по плоскому URL `/{slug}`.

**Методы:**

-   `show(string $slug): Response|View` - отображает опубликованную страницу по slug

**Логика:**

1. Дополнительная проверка `isReserved("/{$slug}")` для защиты от зарезервированных путей (на случай изменений после `route:cache`)
2. Поиск опубликованной страницы типа `page` по slug через скоупы:
    - `Entry::published()` - проверяет `status='published'` и `published_at <= now()`
    - `ofType('page')` - фильтрует по типу контента
    - `where('slug', $slug)` - поиск по текущему slug
3. Возврат 404, если страница не найдена или не опубликована
4. Отображение view `pages.show` с данными Entry

**Особенности:**

-   Использует текущий slug из `entries.slug` (не историю из `entry_slugs`)
-   Поддержка истории slug'ов будет реализована в задаче 93 (редиректы)
-   **Узкий catch**: обрабатывает только кейс "table not found" (42S02/HY000), остальные ошибки логируются и пробрасываются
-   **firstOrFail()**: использует `firstOrFail()` вместо `first() + abort(404)` для единообразия с Laravel-подходом

**Пример ключевого кода:**

```php
// Узкий catch: только "table not found"
try {
    if ($this->pathReservationService->isReserved("/{$slug}")) {
        abort(404);
    }
} catch (\Illuminate\Database\QueryException $e) {
    $code = (string) $e->getCode();
    if (!in_array($code, ['42S02', 'HY000'], true)) {
        report($e); // Логируем неожиданные ошибки
        throw $e;   // Пробрасываем дальше
    }
    if ($code === 'HY000' && !str_contains($e->getMessage(), 'no such table')) {
        report($e);
        throw $e;
    }
}

// firstOrFail() автоматически выбрасывает 404
$entry = Entry::published()
    ->ofType('page')
    ->where('slug', $slug)
    ->with('postType')
    ->firstOrFail();
```

### 2. View

**Файл:** `resources/views/pages/show.blade.php`

Шаблон для отображения публичной страницы.

```blade
@extends('layouts.public')

@section('title', $entry->title)

@section('content')
  <article class="prose">
    <h1>{{ $entry->title }}</h1>
    @php
      // ВАЖНО: До включения санитайзера (задача 35) контент экранируется для безопасности
      // После реализации санитайзера использовать body_html_sanitized и {!! !!}
      $html = data_get($entry->data_json, 'body_html_sanitized');
      $content = data_get($entry->data_json, 'content');
      $bodyHtml = data_get($entry->data_json, 'body_html');
    @endphp

    @if($html !== null)
      {{-- Санитизированный HTML из задачи 35 --}}
      {!! $html !!}
    @elseif($bodyHtml !== null)
      {{-- Временно экранируем до включения санитайзера --}}
      {{ $bodyHtml }}
    @elseif($content !== null)
      {{-- Текстовый контент (безопасен) --}}
      {{ $content }}
    @endif
  </article>
@endsection
```

**Особенности:**

-   Использует layout `layouts.public`
-   Отображает заголовок и контент из `data_json`
-   **XSS-защита**: до включения санитайзера (задача 35) контент экранируется через `{{ }}`
-   После реализации санитайзера будет использоваться `body_html_sanitized` с `{!! !!}`
-   Поддерживает `body_html_sanitized`, `body_html` и `content` в `data_json`

### 3. Layout

**Файл:** `resources/views/layouts/public.blade.php`

Базовый layout для публичных страниц.

```blade
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'StupidCMS')</title>
</head>
<body>
    @yield('content')
</body>
</html>
```

## Производительность

-   **Индекс по `slug`**: добавлен индекс `entries_slug_idx` для оптимизации поиска записей по slug
-   **Составной индекс**: используется индекс `(status, published_at)` для оптимизации скоупа `published()`
-   **Уникальность slug для Page**: реализована через триггеры в миграции `create_entries_table.php` (глобальная уникальность для активных записей типа 'page')
    -   Триггеры `trg_entries_pages_slug_unique_before_ins` и `trg_entries_pages_slug_unique_before_upd` обеспечивают инвариант
    -   Используется составной UNIQUE индекс `entries_unique_active_slug` на `(post_type_id, slug, is_active)`
-   **Один запрос**: поиск Entry выполняется одним запросом без JOIN (при использовании истории slug'ов будет JOIN по `entry_slugs`)
-   **Опциональная оптимизация**: при необходимости можно добавить составной индекс `(post_type_id, slug)` для частых фильтров скоупа

## Безопасность

### XSS-защита

До включения санитайзера (задача 35) контент из `data_json['body_html']` экранируется через `{{ }}` для предотвращения XSS-атак.

После реализации санитайзера:

-   Контент будет храниться в `data_json['body_html_sanitized']`
-   View будет использовать `{!! !!}` для вывода санитизированного HTML
-   В документации явно указано, что контент проходит через санитайзер из задачи 35

### Обработка исключений

Обработка исключений в `isReserved()` ограничена только кейсом "table not found" (коды 42S02 для MySQL, HY000 для SQLite). Остальные ошибки логируются через `report()` и пробрасываются дальше, что предотвращает скрытие реальных прод-ошибок.

## Критерии приёмки

✅ **Контроллер `PageController@show` реализован**

-   Метод `show()` ищет Entry по текущему slug
-   Использует скоупы `published()` и `ofType('page')`
-   Возвращает view `pages.show` с данными Entry

✅ **Published видны, draft/future скрыты (404)**

-   Published страницы отображаются (200)
-   Draft страницы возвращают 404
-   Future published страницы возвращают 404
-   Несуществующие slug'и возвращают 404

✅ **Индекс по `entries.slug` существует**

-   Добавлен индекс `entries_slug_idx` в миграции `2025_11_07_095247_add_slug_index_to_entries_table.php`

✅ **Тесты зелёные**

-   Все тесты проходят: 12 passed, 32 assertions
-   Покрыты все сценарии из спецификации задачи 31

## Связанные задачи

-   **Задача 21**: slugify - генерация slug'ов
-   **Задача 24**: история slug'ов - поддержка будет добавлена в задаче 93
-   **Задача 25**: инварианты публикации - используется скоуп `published()`
-   **Задача 30**: маршрутизация - маршрут `GET /{slug}` определен
-   **Задача 33**: рендерер страницы - будет реализован в будущем
-   **Задача 93**: редиректы для устаревших slug'ов - поддержка истории slug'ов

## Расширение (out of scope)

-   Поддержка локализаций (`/{locale}/{slug}`)
-   Кеш/варианты (AMP, JSON-API)
-   Интеграция со схемой редиректов по старым slug'ам (301) - задача 93
