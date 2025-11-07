# Базовый Blade-layout и partials (Задача 34)

## Обзор

Реализован единый базовый Blade-layout `layouts/app.blade.php` и два partial-шаблона `partials/header.blade.php` и `partials/footer.blade.php`. Все публичные страницы/шаблоны наследуются от базового layout. Partials подключаются **без передачи данных** (используют глобальные хелперы/конфиг/вью-композеры).

**Ключевые особенности:**

-   Страницы наследуют `layouts.app` через `@extends`
-   `header` и `footer` подключаются через `@include` без параметров
-   Partials используют только глобальные функции (`config()`, `url()`, `now()`, `app()`)
-   Layout поддерживает секции `title`, `meta`, `content` и стеки `head`, `scripts`
-   Условная загрузка Vite для совместимости с тестами

**Дата реализации:** 2025-01-07  
**Статус:** ✅ Реализовано, все тесты проходят

---

## Структура файлов

```
resources/views/
 ├─ layouts/
 │   └─ app.blade.php              # Базовый layout
 ├─ partials/
 │   ├─ header.blade.php            # Header partial (без данных)
 │   └─ footer.blade.php            # Footer partial (без данных)
 └─ pages/
     └─ show.blade.php              # Обновлен для использования layouts.app
 └─ home/
     └─ default.blade.php           # Обновлен для использования layouts.app
```

---

## Основные компоненты

### 1. Layout: `resources/views/layouts/app.blade.php`

Базовый layout для всех публичных страниц.

**Особенности:**

-   Использует `@yield('title')` с дефолтным значением `config('app.name')`
-   Поддерживает `@stack('meta')` для мета-тегов (canonical links и т.д.) - используется `@stack` вместо `@yield` для совместимости с `@push('meta')` в страницах
-   Поддерживает `@stack('head')` для дополнительных элементов в `<head>`
-   Поддерживает `@stack('scripts')` для скриптов перед закрывающим `</body>`
-   Условная загрузка Vite: исключает Vite в тестовом окружении и проверяет наличие манифеста в остальных окружениях для избежания ошибок при отсутствии сборки
-   Partials подключены без передачи данных через `@include('partials.header')` и `@include('partials.footer')`
-   Семантичная разметка: `<header>`, `<main>`, `<footer>`
-   Flexbox layout для sticky footer: `min-h-screen flex flex-col` на body, `grow` на main

**Пример использования:**

```blade
@extends('layouts.app')

@section('title', 'Page Title')

@push('meta')
  <link rel="canonical" href="{{ url('/page') }}">
@endpush

@section('content')
  <article>
    <h1>Content</h1>
  </article>
@endsection
```

### 2. Header Partial: `resources/views/partials/header.blade.php`

Header-часть сайта без передачи данных.

**Особенности:**

-   Использует `data-partial="header"` для идентификации в тестах
-   Логотип/название сайта через `config('app.name')`
-   Простая навигация с заглушкой `#about` (можно расширить через меню/опции в будущем)
-   Не требует локальных переменных, использует только глобальные функции

**Используемые функции:**

-   `url('/')` - ссылка на главную
-   `config('app.name')` - название приложения

### 3. Footer Partial: `resources/views/partials/footer.blade.php`

Footer-часть сайта без передачи данных.

**Особенности:**

-   Использует `data-partial="footer"` для идентификации в тестах
-   Автоматический год через `now()->year`
-   Название сайта через `config('app.name')`
-   Не требует локальных переменных

**Используемые функции:**

-   `now()->year` - текущий год
-   `config('app.name')` - название приложения

### 4. Обновленные шаблоны

**`resources/views/pages/show.blade.php`:**

-   Обновлен с `@extends('layouts.public')` на `@extends('layouts.app')`
-   Сохранена поддержка `@push('meta')` для canonical links

**`resources/views/home/default.blade.php`:**

-   Обновлен с самостоятельного HTML на `@extends('layouts.app')`
-   Добавлена секция `@section('title', 'Home')`
-   Контент обернут в `@section('content')`

---

## Критерии приёмки

✅ **Создан layout `layouts/app.blade.php` с секциями `title`, `meta`, `content`, стеками `head`, `scripts`**

-   Layout содержит все необходимые секции и стеки
-   Поддерживает условную загрузку Vite для совместимости с тестами

✅ **Созданы partials `header`, `footer` и подключены **без данных\*\*\*\*

-   Partials используют только глобальные функции
-   Подключены через `@include` без параметров
-   Содержат `data-partial` атрибуты для идентификации в тестах

✅ **Страницы (например, `pages/show`, `home/default`) наследуют layout**

-   `pages/show.blade.php` обновлен для использования `layouts.app`
-   `home/default.blade.php` обновлен для использования `layouts.app`

✅ **Тесты зелёные**

-   Все существующие тесты проходят (200 passed, 500 assertions)
-   Layout корректно рендерится с partials
-   Partials доступны без передачи данных

---

## Тесты

### Feature тесты

**Файл:** `tests/Feature/HomeControllerTest.php`

Все тесты проходят с новым layout:

-   ✅ home route renders default when option not set
-   ✅ home route renders default when entry not found
-   ✅ home route renders default when entry is draft
-   ✅ home route renders published entry
-   ✅ home route renders default when entry is soft deleted
-   ✅ home route renders default when entry has future published at
-   ✅ home route uses cache
-   ✅ home route instantly changes when option changes
-   ✅ home route includes canonical link when entry is set
-   ✅ home route instantly changes when option changes with explicit option check

**Результаты:** 10 passed, 45 assertions

### Проверка partials в HTML

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

## Связанные задачи

-   **Задача 31**: PageController (использует `pages/show.blade.php`)
-   **Задача 32**: HomeController (использует `home/default.blade.php`)
-   **Задача 33**: TemplateResolver (выбор шаблонов)
-   **Задача 45**: ResponseCache/инвалидация (планируется)
-   **Задача 58**: Admin UI: настройки бренда/навигации (расширение partials)

---

## Нефункциональные аспекты

### Производительность

-   **Нет дополнительных запросов**: partials используют только глобальные функции
-   **Кэширование views**: Laravel кэширует скомпилированные Blade-шаблоны
-   **Условная загрузка Vite**: проверка манифеста предотвращает ошибки в тестах

### Надёжность

-   **Graceful degradation**: условная загрузка Vite не ломает приложение при отсутствии манифеста
-   **Без данных**: partials не зависят от передачи переменных, что упрощает их использование

### Безопасность

-   **XSS-защита**: Blade автоматически экранирует вывод через `{{ }}`
-   **Нет инъекций**: использование только глобальных функций Laravel

### Тестируемость

-   **Data-атрибуты**: `data-partial` атрибуты упрощают проверку наличия partials в тестах
-   **Изоляция**: partials можно тестировать независимо без передачи данных
-   **Совместимость**: все существующие тесты проходят без изменений

---

## Расширение (out of scope)

### View Composer для глобальных данных

Если понадобится передавать глобальные данные (например, меню/настройки бренда) и при этом **не нарушить** требование «partials без данных», можно использовать view-composer:

```php
// App\Providers\ViewServiceProvider или AppServiceProvider
use Illuminate\Support\Facades\View;

View::composer(['partials.header', 'partials.footer'], function ($view) {
    $view->with('siteName', config('app.name'));
    // Можно добавить меню, настройки бренда и т.д.
});
```

Но в рамках задачи 34 это **не обязательно**.

### Динамическая навигация

Навигация в header может быть расширена через:

-   Опции CMS (задача 58)
-   Меню из базы данных
-   Blade-компоненты для навигационных элементов

### Мультиязычность

Layout использует `app()->getLocale()` для атрибута `lang`, что готово к расширению для мультиязычности.

---

## Примеры использования

### Создание новой страницы

```blade
{{-- resources/views/pages/about.blade.php --}}
@extends('layouts.app')

@section('title', 'About Us')

@push('meta')
  <meta name="description" content="About our company">
@endpush

@section('content')
  <article class="container mx-auto px-4 py-8">
    <h1>About Us</h1>
    <p>Content here...</p>
  </article>
@endsection
```

### Добавление скриптов

```blade
@push('scripts')
  <script>
    console.log('Page loaded');
  </script>
@endpush
```

### Добавление стилей в head

```blade
@push('head')
  <link rel="stylesheet" href="/custom.css">
@endpush
```

---

## Диагностика и отладка

### Проверка наличия partials

```php
// В тестах
$response = $this->get('/');
$response->assertSee('data-partial="header"', false);
$response->assertSee('data-partial="footer"', false);
```

### Проверка рендера layout

```php
// Прямой рендер layout
$view = view('layouts.app', [
    'title' => 'Test',
    'content' => 'Test content'
]);
echo $view->render();
```

### Проверка partials без данных

```php
// Рендер header
$header = view('partials.header');
echo $header->render();

// Рендер footer
$footer = view('partials.footer');
echo $footer->render();
```

---

## Заключение

Реализация задачи 34 обеспечивает единообразную структуру всех публичных страниц через базовый layout и переиспользуемые partials. Отсутствие передачи данных в partials упрощает их использование и тестирование, а использование глобальных функций гарантирует доступность необходимых данных без дополнительных зависимостей.
