# Code Review: Задача 34 - Базовый Blade-layout и partials

## Обзор изменений

Реализована задача 34 (Базовый Blade-layout и partials) с созданием единого базового layout и двух partial-шаблонов.

**Изменено файлов:** 2
**Создано новых файлов:** 3

**Статус:** ✅ Реализовано, все тесты проходят

---

## 1. resources/views/layouts/app.blade.php

**Статус:** НОВЫЙ ФАЙЛ

```blade
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', config('app.name'))</title>
  @stack('meta')
  @stack('head')
  @if (!app()->environment('testing') && file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css','resources/js/app.js'])
  @endif
</head>
<body class="min-h-screen flex flex-col">
  @include('partials.header')

  <main id="content" class="grow">
    @yield('content')
  </main>

  @include('partials.footer')

  @stack('scripts')
</body>
</html>
```

**Описание:**

- Базовый layout для всех публичных страниц
- Использует `@yield('title')` с дефолтным значением `config('app.name')`
- Поддерживает `@stack('meta')` для мета-тегов (canonical links и т.д.) - используется `@stack` вместо `@yield` для совместимости с `@push('meta')` в страницах
- Поддерживает `@stack('head')` для дополнительных элементов в `<head>`
- Поддерживает `@stack('scripts')` для скриптов перед закрывающим `</body>`
- Условная загрузка Vite: исключает Vite в тестовом окружении и проверяет наличие манифеста в остальных окружениях (`!app()->environment('testing') && file_exists(...)`) для избежания ошибок при отсутствии сборки
- Partials подключены без передачи данных через `@include('partials.header')` и `@include('partials.footer')`
- Семантичная разметка: `<header>`, `<main>`, `<footer>`
- Flexbox layout для sticky footer: `min-h-screen flex flex-col` на body, `grow` на main

---

## 2. resources/views/partials/header.blade.php

**Статус:** НОВЫЙ ФАЙЛ

```blade
<header data-partial="header" class="border-b">
  <div class="container mx-auto px-4 py-4 flex items-center justify-between">
    <a href="{{ url('/') }}" class="font-semibold text-lg">{{ config('app.name') }}</a>
    {{-- Простая навигация; можно расширить позже через меню/опции --}}
    <nav class="flex gap-4">
      <a href="#about">About</a>
    </nav>
  </div>
</header>
```

**Описание:**

- Header-часть сайта без передачи данных
- Использует `data-partial="header"` для идентификации в тестах
- Логотип/название сайта через `config('app.name')`
- Простая навигация с заглушкой `#about` (можно расширить через меню/опции в будущем)
- Не требует локальных переменных, использует только глобальные функции:
  - `url('/')` - ссылка на главную
  - `config('app.name')` - название приложения

---

## 3. resources/views/partials/footer.blade.php

**Статус:** НОВЫЙ ФАЙЛ

```blade
<footer data-partial="footer" class="border-t mt-12">
  <div class="container mx-auto px-4 py-6 text-sm text-gray-500">
    © {{ now()->year }} {{ config('app.name') }}
  </div>
</footer>
```

**Описание:**

- Footer-часть сайта без передачи данных
- Использует `data-partial="footer"` для идентификации в тестах
- Автоматический год через `now()->year`
- Название сайта через `config('app.name')`
- Не требует локальных переменных, использует только глобальные функции:
  - `now()->year` - текущий год
  - `config('app.name')` - название приложения

---

## 4. resources/views/pages/show.blade.php

**Статус:** ИЗМЕНЕН

**Изменения:**

- Обновлен с `@extends('layouts.public')` на `@extends('layouts.app')`
- Сохранена поддержка `@push('meta')` для canonical links

**Полный код:**

```blade
@extends('layouts.app')

@section('title', $entry->title)

@push('meta')
  @if(request()->routeIs('home'))
    {{-- Канонизация: главная страница с записью должна указывать на её прямой URL --}}
    <link rel="canonical" href="{{ url('/' . $entry->slug) }}">
  @endif
@endpush

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

---

## 5. resources/views/home/default.blade.php

**Статус:** ИЗМЕНЕН

**Изменения:**

- Обновлен с самостоятельного HTML на `@extends('layouts.app')`
- Добавлена секция `@section('title', 'Home')`
- Контент обернут в `@section('content')`

**Полный код:**

```blade
@extends('layouts.app')

@section('title', 'Home')

@section('content')
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold">Default Home Page</h1>
  </div>
@endsection
```

---

## Тесты

### Результаты тестирования

**Все тесты проходят:**

```
Tests:    200 passed (500 assertions)
Duration: 9.71s
```

**Feature тесты HomeController:**

```
✓ home route renders default when option not set
✓ home route renders default when entry not found
✓ home route renders default when entry is draft
✓ home route renders published entry
✓ home route renders default when entry is soft deleted
✓ home route renders default when entry has future published at
✓ home route uses cache
✓ home route instantly changes when option changes
✓ home route includes canonical link when entry is set
✓ home route instantly changes when option changes with explicit option check

Tests:    10 passed (45 assertions)
```

### Проверка partials

Для проверки наличия partials в ответе можно использовать:

```php
$response = $this->get('/');
$response->assertSee('data-partial="header"', false);
$response->assertSee('data-partial="footer"', false);
```

### Проверка рендера без данных

Partials можно рендерить напрямую без передачи данных:

```php
$this->view('partials.header')->assertSee(config('app.name'));
$this->view('partials.footer')->assertSee((string) now()->year);
```

---

## Критерии приёмки

✅ **Создан layout `layouts/app.blade.php` с секциями `title`, `meta`, `content`, стеками `head`, `scripts`**

- Layout содержит все необходимые секции и стеки
- Поддерживает условную загрузку Vite для совместимости с тестами

✅ **Созданы partials `header`, `footer` и подключены **без данных****

- Partials используют только глобальные функции
- Подключены через `@include` без параметров
- Содержат `data-partial` атрибуты для идентификации в тестах

✅ **Страницы (например, `pages/show`, `home/default`) наследуют layout**

- `pages/show.blade.php` обновлен для использования `layouts.app`
- `home/default.blade.php` обновлен для использования `layouts.app`

✅ **Тесты зелёные**

- Все существующие тесты проходят (200 passed, 500 assertions)
- Layout корректно рендерится с partials
- Partials доступны без передачи данных

---

## Замечания и улучшения

### Условная загрузка Vite

Использована комбинированная проверка `!app()->environment('testing') && file_exists(public_path('build/manifest.json'))` для условной загрузки Vite. Это исключает Vite в тестовом окружении (без disk I/O) и проверяет наличие манифеста в остальных окружениях для избежания ошибок при отсутствии сборки.

**Преимущества:**

1. Нет disk I/O в тестовом окружении (проверка окружения выполняется первой)
2. Защита от ошибок в development/production при отсутствии сборки
3. Совместимость с тестами без необходимости в манифесте
4. Корректная работа в development без запущенной сборки

### Использование @stack('meta') вместо @yield('meta')

В layout используется `@stack('meta')` вместо `@yield('meta')` для поддержки `@push('meta')` из существующего кода (`pages/show.blade.php`). Это обеспечивает обратную совместимость и большую гибкость.

### Data-атрибуты для тестирования

Partials содержат `data-partial` атрибуты для упрощения проверки их наличия в тестах. Это не нарушает семантику HTML и улучшает тестируемость.

---

## Связанные задачи

- **Задача 31**: PageController (использует `pages/show.blade.php`)
- **Задача 32**: HomeController (использует `home/default.blade.php`)
- **Задача 33**: TemplateResolver (выбор шаблонов)
- **Задача 45**: ResponseCache/инвалидация (планируется)
- **Задача 58**: Admin UI: настройки бренда/навигации (расширение partials)

---

## Заключение

Реализация задачи 34 обеспечивает единообразную структуру всех публичных страниц через базовый layout и переиспользуемые partials. Отсутствие передачи данных в partials упрощает их использование и тестирование, а использование глобальных функций гарантирует доступность необходимых данных без дополнительных зависимостей.

Все критерии приёмки выполнены, тесты проходят, код готов к использованию.

