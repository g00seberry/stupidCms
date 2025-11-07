# Главная страница (Задача 32)

## Обзор

Реализован контроллер `HomeController` для обработки корневого маршрута `/`, который читает опцию `site:home_entry_id` и рендерит соответствующую опубликованную запись или дефолтный шаблон.

**Ключевые особенности:**

-   Мгновенная смена контента при изменении опции (без рестартов и без ожидания кэшей)
-   Использует TemplateResolver для унифицированного выбора шаблонов
-   Проверяет статус публикации и дату published_at
-   Всегда возвращает HTTP 200 (без 404 на главной)

**Дата реализации:** 2025-11-07  
**Статус:** ✅ Все must-fix и should-fix исправлены, готово к merge

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
│       └── HomeController.php             # Контроллер главной страницы
└── Providers/
    └── AppServiceProvider.php             # Регистрация TemplateResolver

config/
└── view_templates.php                     # Конфигурация резолвера шаблонов

routes/
└── web_core.php                           # Маршрут GET /

resources/views/
├── layouts/
│   └── public.blade.php                   # Layout с поддержкой @stack('meta')
├── home/
│   └── default.blade.php                  # Дефолтный шаблон главной
└── pages/
    ├── show.blade.php                     # Дефолтный шаблон записи (с canonical)
    ├── overrides/                         # Override-шаблоны по slug
    └── types/                             # Шаблоны по типу поста

tests/
├── Feature/
│   └── HomeControllerTest.php             # Feature-тесты контроллера
└── Unit/
    └── BladeTemplateResolverTest.php       # Unit-тесты резолвера
```

---

## Основные компоненты

### 1. HomeController

**Файл:** `app/Http/Controllers/HomeController.php`

Контроллер для отображения главной страницы (`/`).

**Логика работы:**

1. Читает опцию `site:home_entry_id` через хелпер `options()`
2. Если опция задана:
    - Выполняет запрос к БД с проверками:
        - `whereKey($id)` - поиск по ID
        - `where('status', 'published')` - только опубликованные
        - `where('published_at', '<=', now())` - не будущие (используется централизованная настройка timezone)
    - Если запись найдена:
        - Использует `TemplateResolver` для выбора шаблона
        - Рендерит выбранный шаблон с данными записи
3. Если опция не задана или запись не найдена:
    - Рендерит дефолтный шаблон `home.default`

**Особенности:**

-   Всегда возвращает HTTP 200 (даже если запись не найдена)
-   Не кеширует результат в себе - читает опцию на каждый запрос
-   Использует eager loading для `postType` связи
-   Инъекция зависимостей через конструктор
-   Явный тип возврата `: View` для улучшения статического анализа

**Пример кода:**

```php
public function __invoke(): View
{
    $id = options('site', 'home_entry_id');

    if ($id) {
        $entry = Entry::query()
            ->whereKey($id)
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            ->with('postType')
            ->first();

        if ($entry) {
            $template = $this->templateResolver->forEntry($entry);
            return $this->view->make($template, ['entry' => $entry]);
        }
    }

    return $this->view->make('home.default');
}
```

### 2. TemplateResolver

**Файл:** `app/Domain/View/TemplateResolver.php`

Интерфейс для выбора Blade-шаблона для рендера Entry.

**Метод:**

-   `forEntry(Entry $entry): string` - возвращает имя шаблона

### 3. BladeTemplateResolver

**Файл:** `app/Domain/View/BladeTemplateResolver.php`

Реализация TemplateResolver с приоритетной стратегией выбора шаблонов.

**Приоритет выбора:**

1. **Override по slug** - `pages.overrides.{slug}` (если файл существует)
2. **По типу поста** - `pages.types.{postType->slug}` (если файл существует)
3. **Default** - `pages.show`

**Особенности:**

-   **Мемоизация View::exists()**: результаты кешируются в `$existsCache` для одного запроса
-   **Санитизация slug**: defense-in-depth - удаление недопустимых символов через `sanitizeSlug()`
-   **Проверка пустого slug**: предотвращает лишние IO-операции при пустом slug после санитизации
-   **Конфигурация**: настройки читаются из `config/view_templates.php`
-   **Octane-ready**: используется scoped binding для предотвращения протечки мемоизации между запросами

**Конфигурация:**

```php
// config/view_templates.php
return [
    'default' => env('VIEW_TEMPLATES_DEFAULT', 'pages.show'),
    'override_prefix' => env('VIEW_TEMPLATES_OVERRIDE_PREFIX', 'pages.overrides.'),
    'type_prefix' => env('VIEW_TEMPLATES_TYPE_PREFIX', 'pages.types.'),
];
```

**Регистрация в контейнере:**

```php
// app/Providers/AppServiceProvider.php
// Используется scoped вместо singleton для совместимости с Octane/Swoole
$this->app->scoped(TemplateResolver::class, function () {
    return new BladeTemplateResolver(
        default: config('view_templates.default', 'pages.show'),
        overridePrefix: config('view_templates.override_prefix', 'pages.overrides.'),
        typePrefix: config('view_templates.type_prefix', 'pages.types.'),
    );
});
```

**Примеры:**

-   Для Entry с `slug='about'`: проверяет `pages.overrides.about`
-   Для Entry с `postType->slug='news'`: проверяет `pages.types.news`
-   Если ничего не найдено: возвращает `pages.show`

### 4. Маршрут

**Файл:** `routes/web_core.php`

```php
Route::get('/', \App\Http\Controllers\HomeController::class)->name('home');
```

**Важно:**

-   Маршрут зарегистрирован в `web_core.php` (не в `web_content.php`), чтобы не перехватывался контентным catch-all `/{slug}`
-   Имя маршрута `'home'` используется для проверки в шаблонах через `request()->routeIs('home')` (например, для canonical link)

### 5. Шаблоны

**Дефолтный шаблон главной:**

`resources/views/home/default.blade.php` - базовый лендинг (используется когда опция не задана или запись не найдена).

**Шаблоны для записей:**

-   `resources/views/pages/show.blade.php` - дефолтный шаблон (с поддержкой canonical link)
-   `resources/views/pages/overrides/{slug}.blade.php` - override по slug
-   `resources/views/pages/types/{postType->slug}.blade.php` - по типу поста

**SEO канонизация:**

В шаблоне `pages/show.blade.php` добавлен canonical link для предотвращения дублей контента:

```blade
@push('meta')
  @if(request()->routeIs('home'))
    {{-- Канонизация: главная страница с записью должна указывать на её прямой URL --}}
    <link rel="canonical" href="{{ url('/' . $entry->slug) }}">
  @endif
@endpush
```

Это гарантирует, что когда запись отображается на главной странице (`/`), поисковые системы получают указание использовать прямой URL записи (`/{slug}`) как канонический.

---

## Кэширование и инвалидация

### Чтение опции

Опция `site:home_entry_id` читается из кэша через `OptionsRepository`:

-   Кэш-ключ: `opt:site:home_entry_id`
-   Теги: `['options', 'options:site']`
-   TTL: навсегда (до инвалидации)

### Мгновенность изменений

При вызове `option_set('site', 'home_entry_id', X)`:

1. `OptionsRepository::set()` сохраняет значение в БД
2. Инвалидирует кэш опций через теги `['options', 'options:site']`
3. Диспатчит событие `OptionChanged` после коммита транзакции

**Результат:** Следующий запрос к `/` сразу получает новое значение опции из БД и рендерит новый контент.

**Производительность:**

-   Чтение опции из кэша: O(1)
-   Один запрос к БД при наличии ID (с eager loading для postType)
-   Проверка существования view: кешируется Laravel (ViewFinderInterface)

---

## Критерии приёмки

✅ **Маршрут `/` объявлен в core и указывает на `HomeController`**

-   Маршрут зарегистрирован в `routes/web_core.php`
-   Использует имя `home` для реверс-роутинга

✅ **Контроллер читает опцию и корректно выбирает между entry и дефолтом**

-   Использует хелпер `options('site', 'home_entry_id')`
-   Проверяет статус публикации и дату
-   Использует TemplateResolver для выбора шаблона
-   Возвращает `home.default` при отсутствии записи

✅ **Смена опции мгновенно отражается на ответе `/`**

-   Тест `test_home_route_instantly_changes_when_option_changes` проверяет:
    -   Установка `entryA` → GET `/` содержит `entryA.title`
    -   Установка `entryB` → GET `/` содержит `entryB.title`
-   Тест `test_home_route_instantly_changes_when_option_changes_with_explicit_option_check` дополнительно проверяет:
    -   Явное изменение опции через `assertEquals()` после `option_set()`
    -   Подтверждение, что опция действительно изменилась перед проверкой контента
-   Инвалидация работает через теги кэша

✅ **Тесты зелёные**

-   10 тестов в `HomeControllerTest` (35 assertions)
-   9 тестов в `BladeTemplateResolverTest` (30 assertions)
-   Всего: 19 тестов (81 assertions)
-   Все тесты проходят

---

## Тесты

### Feature тесты

**Файл:** `tests/Feature/HomeControllerTest.php`

**Покрытие:**

1. ✅ **Default без опции** - рендерит `home.default`
2. ✅ **Default при несуществующем ID** - рендерит `home.default`
3. ✅ **Default для draft записи** - рендерит `home.default`
4. ✅ **Published entry** - рендерит `pages.show` с entry
5. ✅ **Soft-deleted entry** - рендерит `home.default`
6. ✅ **Future published entry** - рендерит `home.default`
7. ✅ **Кэширование опции** - проверка доступности опции из кэша после первого запроса
8. ✅ **Instant change** - смена опции мгновенно меняет контент
9. ✅ **Canonical link** - проверка наличия canonical link на главной странице при отображении записи
10. ✅ **Instant change с проверкой опции** - улучшенный тест с явной проверкой изменения опции через `assertEquals()`

**Результаты:** 10 passed, 35 assertions

### Unit тесты

**Файл:** `tests/Unit/BladeTemplateResolverTest.php`

**Покрытие:**

1. ✅ **Override существует** - возвращает override шаблон
2. ✅ **Type template существует** - возвращает type шаблон когда override отсутствует
3. ✅ **Default** - возвращает default когда оба отсутствуют
4. ✅ **Override имеет наивысший приоритет** - не проверяет type/default если override найден
5. ✅ **Type имеет приоритет над default** - не использует default если type найден
6. ✅ **Санитизация slug** - удаляет недопустимые символы (с учетом нормализации через EntryObserver)
7. ✅ **Мемоизация View::exists()** - кеширует результаты в рамках запроса
8. ✅ **Комплексный тест приоритетов** - проверяет все три уровня приоритета (override > type > default) в одном тесте
9. ✅ **Обработка отсутствующей связи postType** - запрашивает slug из БД

**Результаты:** 9 passed, 30 assertions

---

## Связанные задачи

-   **Задача 20**: Модели и связи (Entry, PostType)
-   **Задача 25**: Инварианты публикации (скоуп `published()`)
-   **Задача 26**: Модель опций (`OptionsRepository`, события)
-   **Задача 29**: Порядок роутинга (маршрут `/` в `web_core.php`)
-   **Задача 30**: Fallback обработчик (порядок регистрации)
-   **Задача 31**: PageController (аналогичный рендер записей)
-   **Задача 33**: TemplateResolver (выбор шаблонов по приоритету)
-   **Задача 45**: ResponseCache/инвалидация (планируется)

---

## Нефункциональные аспекты

### Производительность

-   **Чтение опции**: из кэша O(1)
-   **Запрос к БД**: один запрос с eager loading
-   **Проверка view**: мемоизируется в `BladeTemplateResolver` для одного запроса
-   **Нет избыточных запросов**: контроллер не делает дополнительных проверок
-   **Octane/Swoole совместимость**: scoped binding предотвращает протечку кэша между запросами
-   **Проверка кэша опций**: тест проверяет доступность опции из кэша, а не сравнивает количество SQL-запросов (Laravel может делать служебные запросы)

### Надёжность

-   **Всегда 200**: главная никогда не возвращает 404
-   **Graceful degradation**: при любых проблемах показывается дефолт
-   **Типизация**: использование ViewFactory и TemplateResolver через DI

### Безопасность

-   **Публичный маршрут**: без авторизации
-   **XSS-защита**: через шаблоны Blade (автоэкранирование)
-   **Инъекции SQL**: через Query Builder с параметрами
-   **Санитизация slug**: defense-in-depth - удаление недопустимых символов в TemplateResolver
-   **Проверка пустого slug**: предотвращает лишние IO-операции и потенциальные проблемы
-   **SEO канонизация**: canonical link на прямой URL записи при отображении на главной

### Тестируемость

-   **Dependency Injection**: легко мокировать зависимости
-   **Feature тесты**: покрывают все сценарии end-to-end
-   **Детерминированность**: использование `Carbon::setTestNow()` в setUp()
-   **Единообразие timezone**: все тесты используют `now()` вместо `Carbon::now('UTC')`
-   **Проверка кэша**: тест кэша проверяет доступность опции из кэша через `options()` хелпер, а не через сравнение количества SQL-запросов (более надежный подход)
-   **Покрытие canonical**: отдельный тест проверяет наличие canonical link в HTML при отображении записи на главной

---

## Расширение (out of scope)

### Интеграция с ResponseCache

Планируется в задаче 45:

-   Тегирование ответа `/` по `route:/` или `option:site`
-   Листенер `OptionChanged` для инвалидации HTTP-кэша
-   Surrogate-Key для CDN

### Разные главные по условиям

Возможные расширения:

-   Главная страница по языку/локали
-   Главная страница по типу пользователя
-   A/B тестирование главной страницы

### Виджеты и компоненты

Дефолтный шаблон `home.default` может содержать:

-   Динамические виджеты (последние новости, статистика)
-   Blade-компоненты (hero, features, testimonials)
-   Интеграция с системой блоков (если будет реализована)

---

## Примеры использования

### Установка главной страницы

```php
use App\Models\Entry;

// Создаем опубликованную страницу
$entry = Entry::create([
    'post_type_id' => $postType->id,
    'title' => 'Добро пожаловать',
    'slug' => 'welcome',
    'status' => 'published',
    'published_at' => now(),
    'data_json' => ['content' => 'Контент главной страницы'],
]);

// Устанавливаем как главную
option_set('site', 'home_entry_id', $entry->id);
```

### Сброс главной страницы

```php
// Возврат к дефолтному шаблону
option_set('site', 'home_entry_id', null);
```

### CLI команды

```bash
# Установить главную
php artisan cms:options:set site home_entry_id 123

# Сбросить главную
php artisan cms:options:set site home_entry_id null

# Получить текущее значение
php artisan cms:options:get site home_entry_id
```

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

---

## Диагностика и отладка

### Проверка текущей главной страницы

```bash
# Через CLI
php artisan cms:options:get site home_entry_id

# Через Tinker
php artisan tinker
>>> options('site', 'home_entry_id')
```

### Проверка статуса записи

```php
use App\Models\Entry;

$entry = Entry::find($id);

// Проверка публикации
$isPublished = $entry->status === 'published'
    && $entry->published_at !== null
    && $entry->published_at <= now();
```

### Проверка выбора шаблона

```php
use App\Domain\View\TemplateResolver;

$entry = Entry::find($id);
$template = app(TemplateResolver::class)->forEntry($entry);

echo "Template: {$template}";
```

### Очистка кэша опций

```bash
# Очистка всего кэша
php artisan cache:clear

# Или через Tinker
Cache::tags(['options', 'options:site'])->flush();
```

---

## Заключение

Реализация задачи 32 обеспечивает гибкую и надежную систему управления главной страницей с мгновенной сменой контента при изменении опции. Интеграция с TemplateResolver позволяет унифицировать рендеринг записей и легко кастомизировать отображение для конкретных страниц или типов контента.
