# Blade шаблоны по приоритету (Задача 33)

## Обзор

Реализован сервис `TemplateResolver` для выбора Blade-шаблонов при рендеринге записей (`Entry`) с приоритетной стратегией. Сервис интегрирован в `PageController` и `HomeController` для унифицированного выбора шаблонов.

**Ключевые особенности:**

- Приоритетная стратегия выбора: Override → Type → Default
- Мемоизация проверок существования view в рамках одного запроса
- Санитизация slug для безопасности (defense-in-depth)
- Конфигурируемые префиксы и дефолтный шаблон
- Octane/Swoole совместимость через scoped binding

**Дата реализации:** 2025-01-XX  
**Статус:** ✅ Реализовано, интегрировано и исправлено по ревью

---

## Структура файлов

```
app/
├── Domain/
│   └── View/
│       ├── TemplateResolver.php           # Интерфейс для выбора шаблонов
│       └── BladeTemplateResolver.php      # Реализация с приоритетами и мемоизацией
├── Http/
│   └── Controllers/
│       ├── PageController.php             # Обновлен для использования TemplateResolver
│       └── HomeController.php             # Уже использует TemplateResolver (задача 32)
└── Providers/
    └── AppServiceProvider.php             # Регистрация TemplateResolver

config/
└── view_templates.php                     # Конфигурация резолвера шаблонов

resources/views/
└── pages/
    ├── show.blade.php                     # Default шаблон
    ├── overrides/                         # Override-шаблоны по slug (опционально)
    │   └── {slug}.blade.php
    └── types/                             # Шаблоны по типу поста (опционально)
        └── {postType->slug}.blade.php

tests/
├── Feature/
│   └── FlatUrlRoutingTest.php             # Тесты PageController (используют дефолтный шаблон)
└── Unit/
    └── BladeTemplateResolverTest.php       # Unit-тесты резолвера
```

---

## Основные компоненты

### 1. TemplateResolver (Интерфейс)

**Файл:** `app/Domain/View/TemplateResolver.php`

Интерфейс для выбора Blade-шаблона для рендера Entry.

**Метод:**

- `forEntry(Entry $entry): string` - возвращает имя шаблона

**Пример использования:**

```php
$template = app(TemplateResolver::class)->forEntry($entry);
return view($template, ['entry' => $entry]);
```

### 2. BladeTemplateResolver (Реализация)

**Файл:** `app/Domain/View/BladeTemplateResolver.php`

Реализация TemplateResolver с приоритетной стратегией выбора шаблонов.

**Приоритет выбора:**

1. **Override по slug** - `pages.overrides.{slug}` (если файл существует)
2. **По типу поста** - `pages.types.{postType->slug}` (если файл существует)
3. **Default** - `pages.show`

**Особенности:**

- **Мемоизация View::exists()**: результаты кешируются в `$existsCache` для одного запроса
- **Санитизация slug**: defense-in-depth - удаление недопустимых символов через `sanitizeSlug()`
- **Проверка пустого slug**: предотвращает лишние IO-операции при пустом slug после санитизации
- **Конфигурация**: настройки читаются из `config/view_templates.php`
- **Octane-ready**: используется scoped binding для предотвращения протечки мемоизации между запросами
- **Обработка связи postType**: проверяет загружена ли связь, иначе запрашивает из БД

**Пример кода:**

```php
public function forEntry(Entry $entry): string
{
    // 1) Override по slug (с санитизацией)
    $sanitizedSlug = $this->sanitizeSlug($entry->slug);
    if ($sanitizedSlug !== '') {
        $override = $this->overridePrefix . $sanitizedSlug;
        if ($this->viewExists($override)) {
            return $override;
        }
    }

    // 2) По типу поста (берем slug из связи postType)
    if ($entry->relationLoaded('postType') && $entry->postType) {
        $typeKey = $entry->postType->slug;
    } else {
        $typeKey = $entry->postType()->value('slug') ?? 'page';
    }
    
    // Санитизация типа поста для безопасности
    $sanitizedTypeKey = $this->sanitizeSlug($typeKey);
    if ($sanitizedTypeKey !== '') {
        $typeView = $this->typePrefix . $sanitizedTypeKey;
        if ($this->viewExists($typeView)) {
            return $typeView;
        }
    }

    // 3) Дефолт
    return $this->default;
}
```

### 3. Конфигурация

**Файл:** `config/view_templates.php`

```php
return [
    'default' => env('VIEW_TEMPLATES_DEFAULT', 'pages.show'),
    'override_prefix' => env('VIEW_TEMPLATES_OVERRIDE_PREFIX', 'pages.overrides.'),
    'type_prefix' => env('VIEW_TEMPLATES_TYPE_PREFIX', 'pages.types.'),
];
```

### 4. Регистрация в контейнере

**Файл:** `app/Providers/AppServiceProvider.php`

```php
// Используется scoped вместо singleton для совместимости с Octane/Swoole
$this->app->scoped(TemplateResolver::class, function () {
    return new BladeTemplateResolver(
        default: config('view_templates.default', 'pages.show'),
        overridePrefix: config('view_templates.override_prefix', 'pages.overrides.'),
        typePrefix: config('view_templates.type_prefix', 'pages.types.'),
    );
});
```

### 5. Интеграция в контроллеры

**PageController:**

```php
public function show(string $slug): Response|View
{
    // ... проверки и поиск Entry ...
    
    // Используем сервис для выбора шаблона по приоритету
    $template = $this->templateResolver->forEntry($entry);
    
    return view($template, [
        'entry' => $entry,
    ]);
}
```

**HomeController:**

Уже использует TemplateResolver (реализовано в задаче 32).

---

## Примеры использования

### Создание override-шаблона

Для записи с `slug='about'`:

```bash
# Создать файл resources/views/pages/overrides/about.blade.php
```

```blade
@extends('layouts.public')

@section('content')
  <article class="about-page">
    <h1>{{ $entry->title }}</h1>
    {{-- Кастомный контент для страницы "О нас" --}}
  </article>
@endsection
```

### Создание шаблона по типу поста

Для записей типа `news`:

```bash
# Создать файл resources/views/pages/types/news.blade.php
```

```blade
@extends('layouts.public')

@section('content')
  <article class="news-page">
    <h1>{{ $entry->title }}</h1>
    <time>{{ $entry->published_at->format('d.m.Y') }}</time>
    {{-- Контент новости --}}
  </article>
@endsection
```

### Проверка выбора шаблона

```php
use App\Domain\View\TemplateResolver;

$entry = Entry::find($id);
$template = app(TemplateResolver::class)->forEntry($entry);

echo "Template: {$template}";
```

---

## Критерии приёмки

✅ **Создан сервис TemplateResolver и реализация BladeTemplateResolver**

- Интерфейс `TemplateResolver` определен
- Реализация `BladeTemplateResolver` с приоритетной стратегией
- Мемоизация проверок View::exists()
- Санитизация slug для безопасности

✅ **Контроллеры используют сервис вместо хардкода имён view**

- `PageController` обновлен для использования TemplateResolver
- `HomeController` уже использует TemplateResolver (задача 32)
- Удален хардкод `view('pages.show')` из PageController
- Удалена проверка резервирования пути из PageController (после ревью)

✅ **Три сценария приоритета покрыты тестами**

- Override имеет наивысший приоритет
- Type template имеет приоритет над default
- Default используется когда override и type отсутствуют
- Все тесты проходят (12 тестов, 44 assertions)

✅ **Исправления после ревью**

- Удалена проверка `isReserved()` из PageController (проверка выполняется на уровне роутинга)
- Обновлена спецификация: заменено `postType.template` на `pages.types.{postType->slug}`
- Добавлены тесты на незагруженную связь postType и пустой slug после санитизации
- Добавлен smoke-тест на приоритет override vs type
- Оптимизирован middleware: try/catch только в testing environment

---

## Тесты

### Unit тесты

**Файл:** `tests/Unit/BladeTemplateResolverTest.php`

**Покрытие:**

1. ✅ **Override существует** - возвращает override шаблон
2. ✅ **Type template существует** - возвращает type шаблон когда override отсутствует
3. ✅ **Default** - возвращает default когда оба отсутствуют
4. ✅ **Override имеет наивысший приоритет** - не проверяет type/default если override найден
5. ✅ **Type имеет приоритет над default** - не использует default если type найден
6. ✅ **Санитизация slug** - удаляет недопустимые символы
7. ✅ **Мемоизация View::exists()** - кеширует результаты в рамках запроса
8. ✅ **Комплексный тест приоритетов** - проверяет все три уровня приоритета
9. ✅ **Обработка отсутствующей связи postType** - запрашивает slug из БД, предотвращает N+1 запросы
10. ✅ **Пустой slug после санитизации** - пропускает проверку override, переходит к type/default
11. ✅ **Пустой slug после санитизации fallback** - проверка fallback на default при пустом slug
12. ✅ **Smoke-тест: override vs type** - проверяет что override выигрывает при наличии обоих шаблонов

**Результаты:** 12 passed, 44 assertions

### Feature тесты

**Файл:** `tests/Feature/FlatUrlRoutingTest.php`

Тесты для PageController продолжают работать, так как по умолчанию TemplateResolver возвращает `pages.show` при отсутствии override и type шаблонов.

---

## Связанные задачи

- **Задача 20**: Модели и связи (Entry, PostType)
- **Задача 31**: PageController (использует TemplateResolver)
- **Задача 32**: HomeController (использует TemplateResolver)
- **Задача 21**: Slugify (нормализация slug)
- **Задача 24**: История slug'ов

---

## Нефункциональные аспекты

### Производительность

- **Мемоизация View::exists()**: результаты кешируются в рамках одного запроса
- **Минимум IO-операций**: проверка пустого slug предотвращает лишние вызовы View::exists()
- **Eager loading**: контроллеры загружают связь postType заранее
- **Octane/Swoole совместимость**: scoped binding предотвращает протечку кэша между запросами

### Надёжность

- **Graceful degradation**: всегда возвращает дефолтный шаблон при отсутствии override/type
- **Типизация**: использование интерфейса через DI
- **Обработка отсутствующей связи**: запрашивает slug из БД если связь не загружена

### Безопасность

- **Санитизация slug**: defense-in-depth - удаление недопустимых символов
- **Проверка пустого slug**: предотвращает потенциальные проблемы
- **XSS-защита**: через шаблоны Blade (автоэкранирование)

### Тестируемость

- **Dependency Injection**: легко мокировать зависимости
- **Unit тесты**: покрывают все сценарии приоритетов
- **Feature тесты**: проверяют интеграцию с контроллерами

---

## Расширение (out of scope)

### Override на уровне БД

Возможное расширение:

- Поле `entries.template_override` в БД
- Разрешение относительных путей
- Валидация существования шаблона

### Поддержка тем

Возможное расширение:

- Префикс `themes::{name}::` для шаблонов
- Каскад `theme > app` для поиска шаблонов
- Переключение тем через опции

### Развилка по языкам/локалям

Возможное расширение:

- Путь `pages/overrides/{locale}/{slug}`
- Автоматическое определение локали
- Fallback на дефолтную локаль

### Кеширование результата

Возможное расширение:

- Кеширование `forEntry()` по `entry_id` + `updated_at`
- Инвалидация при изменении записи
- Инвалидация при деплое шаблонов

---

## Заключение

Реализация задачи 33 обеспечивает гибкую и надежную систему выбора Blade-шаблонов с приоритетной стратегией. Интеграция в PageController и HomeController позволяет унифицировать рендеринг записей и легко кастомизировать отображение для конкретных страниц или типов контента.

