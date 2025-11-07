# Модель опций сайта (Home Page)

## Обзор

Система опций сайта позволяет хранить и управлять настройками через репозиторий с кэшированием. Основная опция `site:home_entry_id` определяет, какая опубликованная запись будет отображаться на главной странице (`/`).

## Структура

```
app/
├── Domain/Options/
│   └── OptionsRepository.php          # Репозиторий с кэшированием
├── Events/
│   └── OptionChanged.php              # Событие изменения опции
├── Helpers/
│   └── options.php                    # Хелперы options() и option_set()
├── Http/
│   ├── Controllers/
│   │   └── HomeController.php         # Контроллер для маршрута /
│   └── Requests/
│       └── OptionsRequest.php          # Валидация для Admin API
└── Console/Commands/
    ├── OptionsSetCommand.php          # CLI: установка опции
    └── OptionsGetCommand.php           # CLI: получение опции
config/
└── options.php                        # Конфигурация допустимых опций
```

## Схема данных

Таблица `options`:

-   `id` — первичный ключ
-   `namespace` (varchar) — пространство имён (например, `site`)
-   `key` (varchar) — ключ опции (например, `home_entry_id`)
-   `value_json` (JSON) — значение (примитив/объект/массив)
-   `created_at`/`updated_at` — временные метки

Уникальный индекс: `UNIQUE(namespace, key)`

## OptionsRepository

Репозиторий для работы с опциями с поддержкой кэширования:

```php
use App\Domain\Options\OptionsRepository;

$repository = app(OptionsRepository::class);

// Получение опции
$value = $repository->get('site', 'home_entry_id', null);
$intValue = $repository->getInt('site', 'home_entry_id', null);

// Установка опции
$repository->set('site', 'home_entry_id', 123);
```

### Кэширование

-   Все чтения опций кэшируются навсегда (`rememberForever`)
-   При изменении опции кэш инвалидируется через теги `['options', 'options:{namespace}']`
-   **Fallback для драйверов без тегов**: автоматическая детекция через `getStore()` и `method_exists($store, 'tags')`
    -   Если теги поддерживаются → инвалидация через `tags()->forget()`
    -   Если теги не поддерживаются (array, file) → точечная инвалидация через `forget()`

## Хелперы

Удобные функции для работы с опциями:

```php
// Чтение опции
$homeId = options('site', 'home_entry_id', null);

// Установка опции
option_set('site', 'home_entry_id', 123);
```

Хелперы автоматически загружаются через `composer.json > autoload.files`.

## HomeController

Контроллер для обработки корневого маршрута `/`:

```php
Route::get('/', \App\Http\Controllers\HomeController::class);
```

**Логика работы:**

1. Читает опцию `site:home_entry_id` через `getInt()` (корректная обработка `0`/пустых строк)
2. Если опция задана и запись найдена и опубликована → рендерит `pages.show`
3. Иначе → рендерит `home.default`
4. Всегда возвращает HTTP 200 (без 404)

**Важно:**

-   Использует `getInt()` для получения опции (защита от строковых значений)
-   Использует скоуп `Entry::published()` для проверки доступности записи

## CLI команды

### Установка опции

```bash
php artisan cms:options:set site home_entry_id 123
php artisan cms:options:set site home_entry_id null  # Сброс опции (JSON-литерал)
```

**Особенности:**

-   **Парсинг JSON-литералов**: команда автоматически парсит JSON-литералы (`null`, числа, строки)
    -   `null` → сохраняется как `null` (не строка `"null"`)
    -   `123` → сохраняется как `int` (не строка `"123"`)
    -   `"text"` → сохраняется как строка
-   **Валидация для `site:home_entry_id`:**
    -   ID должен быть положительным целым числом
    -   Запись с указанным ID должна существовать
    -   `null` разрешён для сброса опции
-   **Allow-list**: только опции из `config/options.php` могут быть установлены

### Получение опции

```bash
php artisan cms:options:get site home_entry_id
# Выводит JSON-значение в STDOUT
```

## Валидация Admin API

`OptionsRequest` обеспечивает валидацию для установки опций через API:

```php
use App\Http\Requests\OptionsRequest;

public function update(OptionsRequest $request)
{
    $validated = $request->validated();
    option_set(
        $validated['namespace'],
        $validated['key'],
        $validated['value'] ?? null
    );
}
```

**Правила валидации:**

-   **Allow-list**: проверка через `config/options.php` — только разрешённые опции могут быть установлены
-   **Для `site:home_entry_id`:**
    -   `value`: `nullable|integer|min:1`
    -   Кастомное правило: проверка существования записи
-   Для неразрешённых опций применяется базовая валидация (без специальных правил)

## Событие OptionChanged

При изменении опции отправляется событие **после успешного коммита транзакции**:

```php
use App\Events\OptionChanged;
use Illuminate\Support\Facades\Event;

Event::listen(OptionChanged::class, function (OptionChanged $event) {
    // Инвалидация ResponseCache
    // Логирование в аудит (с diff через oldValue)
    // и т.д.
});
```

**Свойства события:**

-   `namespace` — пространство имён
-   `key` — ключ опции
-   `value` — новое значение
-   `oldValue` — предыдущее значение (для аудита и diff)

**Важно:** Событие отправляется через `DB::afterCommit()`, что гарантирует:

-   Событие не сработает при откате транзакции
-   Слушатели видят только "чистое" состояние после коммита
-   Идеально для инвалидации внешних кэшей и аудита

## Доменные правила для `site:home_entry_id`

1. **Значение**: `null` или положительный `int`
2. **Валидация**: при установке допустимы только ID существующих записей
3. **Публичный просмотр `/`**:
    - Опция не задана → рендер `home.default` (200)
    - Опция задана, но запись не найдена/недоступна → рендер `home.default` (200)
    - Запись найдена и опубликована → рендер `pages.show` с Entry

**Intentionally без 404** на `/` — главная всегда отвечает 200.

## Примеры использования

### Установка главной страницы

```php
use App\Models\Entry;

// Создаём опубликованную страницу
$entry = Entry::create([
    'post_type_id' => $postType->id,
    'title' => 'Главная страница',
    'slug' => 'home',
    'status' => 'published',
    'published_at' => now('UTC'),
    'data_json' => [],
]);

// Устанавливаем как главную
option_set('site', 'home_entry_id', $entry->id);
```

### Сброс главной страницы

```php
option_set('site', 'home_entry_id', null);
```

### Чтение опции

```php
$homeId = options('site', 'home_entry_id');
if ($homeId) {
    $entry = Entry::published()->find($homeId);
    // ...
}
```

## Кэширование и инвалидация

### Кэш опций

-   Ключ: `opt:{namespace}:{key}`
-   Теги: `['options', 'options:{namespace}']`
-   TTL: навсегда (до инвалидации)

### Инвалидация

При вызове `OptionsRepository::set()` (внутри транзакции):

1. Получение старого значения из БД (для события)
2. Сохранение в БД через `updateOrCreate()`
3. Инвалидация кэша опций:
    - Если теги поддерживаются → `tags(['options', 'options:{namespace}'])->forget(key)`
    - Если теги не поддерживаются → `forget(key)` (точечная инвалидация)
4. Регистрация события через `DB::afterCommit()` (отправка после коммита)

**Листенер события** может:

-   Очищать ResponseCache с тегом `option:site`
-   Логировать изменения в аудит
-   Обновлять индексы поиска

## Тестирование

### Unit тесты

-   `tests/Unit/OptionsRepositoryTest.php` (12 тестов)
    -   Чтение/запись опций
    -   Кэширование и инвалидация
    -   События (включая проверку `oldValue`)
    -   События после коммита транзакции
    -   Fallback для драйверов без тегов
    -   Параллельные set() (консистентность)

### Feature тесты

-   `tests/Feature/HomeControllerTest.php` (7 тестов)

    -   Рендеринг дефолтной страницы
    -   Рендеринг опубликованной записи
    -   Обработка edge cases (draft, soft-deleted, future date)
    -   Использование кэша

-   `tests/Feature/OptionsCommandsTest.php` (8 тестов)

    -   CLI команды get/set
    -   Валидация в командах
    -   Парсинг JSON-литералов
    -   Различение `null` и строки `"null"`
    -   Проверка allow-list

-   `tests/Feature/OptionsValidationTest.php` (6 тестов)
    -   Валидация через OptionsRequest
    -   Проверка существования записи
    -   Проверка allow-list

**Всего: 33 теста, все проходят ✅**

### Запуск тестов

```bash
# Все тесты опций
php artisan test --filter=Options

# Тесты HomeController
php artisan test --filter=HomeController

# Все вместе
php artisan test --filter="Options|Home"
```

## Конфигурация

### Allow-list опций

Файл `config/options.php` определяет список допустимых опций:

```php
return [
    'allowed' => [
        'site' => [
            'home_entry_id',
        ],
    ],
];
```

**Использование:**

-   CLI команды проверяют allow-list перед установкой
-   `OptionsRequest` применяет специальные правила только для разрешённых опций
-   Неразрешённые опции отклоняются с ошибкой

## Интеграция

### Регистрация в контейнере

`OptionsRepository` зарегистрирован как singleton в `AppServiceProvider`:

```php
$this->app->singleton(OptionsRepository::class, function ($app) {
    return new OptionsRepository($app->make(CacheRepository::class));
});
```

### Автозагрузка хелперов

Файл `app/Helpers/options.php` загружается автоматически через `composer.json`:

```json
{
    "autoload": {
        "files": ["app/Helpers/options.php"]
    }
}
```

После изменения `composer.json` выполните:

```bash
composer dump-autoload
```

## Аудит (планируется)

При установке `home_entry_id` должна записываться запись в `audits`:

-   `action = 'options.updated'`
-   `subject_type = 'option'`
-   `subject_id = 'site:home_entry_id'`
-   `diff_json = {"old": <prev>, "new": <next>}`

**Готовность к аудиту:**

-   Событие `OptionChanged` уже содержит `oldValue` и `value` для формирования diff
-   Событие отправляется после коммита транзакции, что гарантирует консистентность данных

Реализация аудита — в отдельной задаче.

## Нефункциональные аспекты

-   **Безопасность**:
    -   Только админ может изменять опции (middleware `admin.auth` — в задаче 50)
    -   Allow-list в `config/options.php` ограничивает набор допустимых опций
-   **Производительность**:
    -   Все чтения опций — из кэша (`rememberForever`)
    -   Запись редкая и дороже (инвалидация тегов или точечная инвалидация)
    -   Fallback для драйверов без тегов (array, file) — точечная инвалидация вместо flush
-   **Надёжность**:
    -   Дефолтный рендер на `/` при любых проблемах с опцией/записью
    -   События отправляются только после успешного коммита (защита от "грязного" состояния)
    -   Транзакции гарантируют атомарность изменений
-   **Локализация**: тексты ошибок валидатора — через `lang/ru/validation.php`

## Расширение (out of scope)

-   Страница «Настройки сайта» в админке с подбором страницы-главной (задача 58)
-   Поддержка разных главных страниц по типам постов/условиям
-   Кэш-ключи на уровне CDN (Surrogate-Key = `option:site`)
