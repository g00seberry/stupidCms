# План системы тестирования проекта

## Обзор

Система тестирования для headless CMS на базе Laravel 12 с использованием Pest PHP. Система спроектирована для прозрачности, структурированности, расширяемости и модульности.

**Дата создания:** 2025-01-17  
**Версия:** 1.0

---

## 1. Архитектура системы

### 1.1. Принципы организации

-   **Модульность**: Тесты организованы по доменным модулям (`Domain/`)
-   **Иерархия**: Unit → Feature → Integration → E2E
-   **Изоляция**: Каждый модуль может запускаться независимо
-   **Прозрачность**: Четкая структура, понятные имена, документация
-   **Расширяемость**: Легко добавлять новые тесты и модули

### 1.2. Структура директорий

```
tests/
├── Unit/                          # Unit-тесты (изолированные компоненты)
│   ├── Domain/
│   │   ├── Auth/
│   │   │   ├── JwtServiceTest.php
│   │   │   └── RefreshTokenRepositoryTest.php
│   │   ├── Media/
│   │   │   ├── Actions/
│   │   │   ├── Services/
│   │   │   └── Validation/
│   │   ├── Entries/
│   │   ├── Options/
│   │   ├── Plugins/
│   │   ├── PostTypes/
│   │   ├── Routing/
│   │   ├── Sanitizer/
│   │   ├── Search/
│   │   └── View/
│   ├── Models/                    # Тесты моделей (scopes, accessors, mutators)
│   ├── Rules/                     # Тесты валидационных правил
│   ├── Support/                   # Тесты вспомогательных классов
│   └── Helpers/                   # Тесты хелперов
│
├── Feature/                       # Feature-тесты (HTTP, интеграция компонентов)
│   ├── Api/
│   │   ├── Admin/
│   │   │   ├── V1/
│   │   │   │   ├── Auth/
│   │   │   │   │   └── AuthenticationTest.php
│   │   │   │   ├── Entries/
│   │   │   │   │   └── EntryManagementTest.php
│   │   │   │   ├── Media/
│   │   │   │   │   └── MediaManagementTest.php
│   │   │   │   ├── Options/
│   │   │   │   │   └── OptionsManagementTest.php
│   │   │   │   ├── Plugins/
│   │   │   │   │   └── PluginsManagementTest.php
│   │   │   │   ├── PostTypes/
│   │   │   │   │   └── PostTypesManagementTest.php
│   │   │   │   ├── Search/
│   │   │   │   │   └── SearchAdminTest.php
│   │   │   │   ├── Taxonomies/
│   │   │   │   │   └── TaxonomiesManagementTest.php
│   │   │   │   └── Terms/
│   │   │   │       └── TermsManagementTest.php
│   │   │   └── Public/
│   │   │       ├── Media/
│   │   │       │   └── PublicMediaTest.php
│   │   │       └── Search/
│   │   │           └── PublicSearchTest.php
│   │   └── Web/
│   │       ├── Content/
│   │       │   └── ContentRenderingTest.php
│   │       └── Pages/
│   │           └── PagesTest.php
│   │
│   ├── Domain/                    # Feature-тесты доменной логики
│   │   ├── Media/
│   │   │   ├── MediaUploadFlowTest.php
│   │   │   ├── MediaProcessingTest.php
│   │   │   └── MediaValidationTest.php
│   │   ├── Entries/
│   │   │   ├── EntryPublishingTest.php
│   │   │   └── EntrySlugGenerationTest.php
│   │   ├── Plugins/
│   │   │   ├── PluginActivationTest.php
│   │   │   └── PluginRoutesTest.php
│   │   ├── Search/
│   │   │   ├── SearchIndexingTest.php
│   │   │   └── SearchQueryTest.php
│   │   └── Routing/
│   │       └── PathReservationTest.php
│   │
│   └── Integration/               # Интеграционные тесты
│       ├── MediaProcessingPipelineTest.php
│       ├── SearchReindexingTest.php
│       └── PluginSystemTest.php
│
├── Helpers/                       # Вспомогательные классы для тестов
│   ├── TestCase.php              # Базовый класс для всех тестов
│   ├── CreatesApplication.php
│   ├── Database/
│   │   ├── RefreshDatabase.php
│   │   └── DatabaseTransactions.php
│   ├── Factories/                 # Дополнительные фабрики для тестов
│   ├── Traits/
│   │   ├── AuthenticatesUsers.php
│   │   ├── CreatesMedia.php
│   │   ├── CreatesEntries.php
│   │   └── MocksServices.php
│   └── Fixtures/                  # Фикстуры данных
│       ├── media/
│       ├── entries/
│       └── users/
│
├── Pest.php                       # Главный конфигурационный файл Pest
└── Modules/                       # Конфигурация модулей для запуска
    ├── auth.php
    ├── entries.php
    ├── media.php
    ├── options.php
    ├── plugins.php
    ├── post-types.php
    ├── routing.php
    ├── sanitizer.php
    ├── search.php
    └── view.php
```

---

## 2. Установка и настройка

### 2.1. Установка зависимостей

```bash
composer require pestphp/pest --dev --with-all-dependencies
composer require pestphp/pest-plugin-laravel --dev
composer require pestphp/pest-plugin-parallel --dev
```

### 2.2. Инициализация Pest

```bash
php artisan pest:install
```

### 2.3. Конфигурационные файлы

#### `tests/Pest.php` (главный конфиг)

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class, RefreshDatabase::class)->in('Feature');

// Модульные тесты без БД
uses(TestCase::class)->in('Unit');

// Загрузка модульных конфигураций
$modules = glob(__DIR__ . '/Modules/*.php');
foreach ($modules as $module) {
    require $module;
}
```

#### `tests/Modules/media.php` (пример модульной конфигурации)

```php
<?php

declare(strict_types=1);

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Группировка тестов модуля Media
uses(TestCase::class, RefreshDatabase::class)
    ->group('module:media')
    ->in('Feature/Api/Admin/V1/Media')
    ->in('Feature/Domain/Media')
    ->in('Unit/Domain/Media');
```

---

## 3. Модульная организация

### 3.1. Модули проекта

На основе структуры `app/Domain/` выделены следующие модули:

1. **Auth** — аутентификация и авторизация
2. **Entries** — управление записями контента
3. **Media** — управление медиа-файлами
4. **Options** — управление опциями системы
5. **Plugins** — система плагинов
6. **PostTypes** — типы записей
7. **Routing** — резервирование путей
8. **Sanitizer** — санитизация контента
9. **Search** — поиск и индексация
10. **View** — рендеринг шаблонов

### 3.2. Запуск тестов по модулям

#### Через Pest группы

```bash
# Все тесты модуля Media
php artisan test --group=module:media

# Все тесты модуля Entries
php artisan test --group=module:entries

# Все тесты модуля Auth
php artisan test --group=module:auth
```

#### Через пути

```bash
# Тесты модуля Media
php artisan test tests/Feature/Api/Admin/V1/Media
php artisan test tests/Feature/Domain/Media
php artisan test tests/Unit/Domain/Media

# Все тесты модуля (через скрипт)
composer test:module media
```

#### Через composer scripts

Добавить в `composer.json`:

```json
{
    "scripts": {
        "test": "php artisan test",
        "test:unit": "php artisan test --testsuite=Unit",
        "test:feature": "php artisan test --testsuite=Feature",
        "test:module": ["@php artisan test --group=module:${MODULE}"],
        "test:module:auth": "php artisan test --group=module:auth",
        "test:module:entries": "php artisan test --group=module:entries",
        "test:module:media": "php artisan test --group=module:media",
        "test:module:options": "php artisan test --group=module:options",
        "test:module:plugins": "php artisan test --group=module:plugins",
        "test:module:post-types": "php artisan test --group=module:post-types",
        "test:module:routing": "php artisan test --group=module:routing",
        "test:module:sanitizer": "php artisan test --group=module:sanitizer",
        "test:module:search": "php artisan test --group=module:search",
        "test:module:view": "php artisan test --group=module:view",
        "test:parallel": "php artisan test --parallel"
    }
}
```

---

## 4. Типы тестов

### 4.1. Unit-тесты

**Назначение**: Тестирование изолированных компонентов без зависимостей от БД, файловой системы, HTTP.

**Примеры**:

-   Сервисы (`JwtService`, `Slugifier`)
-   Валидаторы (`MediaValidationPipeline`, `CorruptionValidator`)
-   Value Objects
-   Утилиты и хелперы
-   Правила валидации (`UniqueEntrySlug`, `ReservedSlug`)

**Структура**:

```php
<?php

declare(strict_types=1);

use App\Domain\Media\Services\ExifManager;
use Tests\TestCase;

test('extracts EXIF data from image', function () {
    $manager = new ExifManager();
    $file = UploadedFile::fake()->image('photo.jpg');

    $exif = $manager->extract($file);

    expect($exif)->toBeArray()
        ->and($exif)->toHaveKey('width');
});
```

### 4.2. Feature-тесты

**Назначение**: Тестирование HTTP-эндпоинтов, интеграции компонентов, бизнес-логики.

**Примеры**:

-   API-контроллеры (CRUD операции)
-   Доменные действия (`MediaStoreAction`, `EntryPublishingService`)
-   Интеграция сервисов
-   Обработка событий и слушателей

**Структура**:

```php
<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create entry', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'admin')
        ->postJson('/api/v1/admin/entries', [
            'title' => 'Test Entry',
            'post_type_id' => 1,
            'status' => 'draft',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'title', 'slug']]);

    $this->assertDatabaseHas('entries', [
        'title' => 'Test Entry',
        'author_id' => $admin->id,
    ]);
});
```

### 4.3. Integration-тесты

**Назначение**: Тестирование полных сценариев с реальными зависимостями (БД, файловая система, внешние сервисы).

**Примеры**:

-   Полный цикл обработки медиа (загрузка → валидация → сохранение → генерация вариантов)
-   Реиндексация поиска
-   Активация плагина с перезагрузкой роутов

---

## 5. Вспомогательные классы и трейты

### 5.1. `tests/Helpers/TestCase.php`

```php
<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
```

### 5.2. Трейты для тестов

#### `tests/Helpers/Traits/AuthenticatesUsers.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use App\Models\User;

trait AuthenticatesUsers
{
    protected function asAdmin(): self
    {
        $admin = User::factory()->admin()->create();
        return $this->actingAs($admin, 'admin');
    }

    protected function asUser(): self
    {
        $user = User::factory()->create();
        return $this->actingAs($user);
    }
}
```

#### `tests/Helpers/Traits/CreatesMedia.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use App\Models\Media;
use Illuminate\Http\UploadedFile;

trait CreatesMedia
{
    protected function createMediaFile(array $attributes = []): Media
    {
        return Media::factory()->create($attributes);
    }

    protected function createUploadedFile(string $name = 'test.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 600);
    }
}
```

---

## 6. Конфигурация PHPUnit/Pest

### 6.1. `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_DATABASE" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
    </php>
</phpunit>
```

---

## 7. Покрытие кода

### 7.1. Настройка

```bash
composer require phpunit/php-code-coverage --dev
```

### 7.2. Генерация отчета

```bash
php artisan test --coverage
php artisan test --coverage --min=80
```

### 7.3. Модульное покрытие

```bash
# Покрытие конкретного модуля
php artisan test --group=module:media --coverage
```

---

## 8. CI/CD интеграция

### 8.1. GitHub Actions (пример)

```yaml
name: Tests

on: [push, pull_request]

jobs:
    test:
        runs-on: ubuntu-latest

        strategy:
            matrix:
                module:
                    [
                        auth,
                        entries,
                        media,
                        options,
                        plugins,
                        post-types,
                        routing,
                        sanitizer,
                        search,
                        view,
                    ]

        steps:
            - uses: actions/checkout@v3
            - uses: shivammathur/setup-php@v2
              with:
                  php-version: "8.3"
            - run: composer install
            - run: php artisan test --group=module:${{ matrix.module }}
```

---

## 9. Документация тестов

### 9.1. README для каждого модуля

Создать `tests/Feature/Api/Admin/V1/Media/README.md`:

````markdown
# Тесты модуля Media

## Описание

Тесты для управления медиа-файлами через админский API.

## Покрытие

-   Загрузка файлов
-   Обновление метаданных
-   Удаление и восстановление
-   Генерация вариантов
-   Валидация файлов

## Запуск

```bash
composer test:module:media
```
````

````

### 9.2. Общий README

Создать `tests/README.md` с описанием структуры и правил написания тестов.

---

## 10. План внедрения

### Этап 1: Базовая настройка (1-2 дня)
- [ ] Установка Pest и зависимостей
- [ ] Создание базовой структуры директорий
- [ ] Настройка `Pest.php` и `phpunit.xml`
- [ ] Создание `TestCase` и базовых трейтов
- [ ] Настройка composer scripts

### Этап 2: Модульная конфигурация (1 день)
- [ ] Создание конфигурационных файлов для всех модулей
- [ ] Настройка групп тестов
- [ ] Тестирование запуска по модулям

### Этап 3: Примеры тестов (2-3 дня)
- [ ] Unit-тесты для 2-3 ключевых сервисов
- [ ] Feature-тесты для 2-3 API-эндпоинтов
- [ ] Документация примеров

### Этап 4: Расширение покрытия (постепенно)
- [ ] Покрытие всех модулей Unit-тестами
- [ ] Покрытие всех API-эндпоинтов Feature-тестами
- [ ] Интеграционные тесты для критичных сценариев

### Этап 5: Автоматизация (1 день)
- [ ] Настройка CI/CD
- [ ] Настройка покрытия кода
- [ ] Документация

---

## 11. Метрики и отчетность

### 11.1. Ключевые метрики
- Покрытие кода по модулям
- Количество тестов по типам (Unit/Feature/Integration)
- Время выполнения тестов
- Процент успешных тестов

### 11.2. Отчеты
- Еженедельный отчет по покрытию
- Отчет по времени выполнения
- Анализ провалившихся тестов

---

## 12. Best Practices

### 12.1. Именование
- Тесты: `test('описание поведения', function () { ... })`
- Группы: `module:media`, `api:admin`, `feature:upload`
- Файлы: `MediaStoreActionTest.php`, `EntryManagementTest.php`

### 12.2. Структура теста
```php
test('admin can update entry', function () {
    // Arrange (Given)
    $admin = User::factory()->admin()->create();
    $entry = Entry::factory()->create();

    // Act (When)
    $response = $this->actingAs($admin, 'admin')
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'title' => 'Updated Title',
        ]);

    // Assert (Then)
    $response->assertStatus(200);
    $this->assertDatabaseHas('entries', [
        'id' => $entry->id,
        'title' => 'Updated Title',
    ]);
});
````

### 12.3. Изоляция

-   Каждый тест независим
-   Использование `RefreshDatabase` для Feature-тестов
-   Моки для внешних зависимостей
-   Очистка после тестов

### 12.4. Производительность

-   Unit-тесты без БД
-   Параллельный запуск где возможно
-   Оптимизация фабрик
-   Кэширование миграций

---

## 13. Расширяемость

### 13.1. Добавление нового модуля

1. Создать директории в `tests/Unit/Domain/NewModule/`
2. Создать директории в `tests/Feature/Domain/NewModule/`
3. Создать `tests/Modules/new-module.php`
4. Добавить composer script `test:module:new-module`

### 13.2. Добавление новых типов тестов

1. Создать директорию (например, `tests/Performance/`)
2. Настроить в `Pest.php`
3. Добавить в документацию

---

## 14. Поддержка и обновление

### 14.1. Регулярные задачи

-   Обновление зависимостей (ежемесячно)
-   Рефакторинг устаревших тестов
-   Оптимизация медленных тестов
-   Обновление документации

### 14.2. Контроль качества

-   Code review для всех тестов
-   Проверка покрытия перед мерджем
-   Анализ провалившихся тестов

---

## Заключение

Данная система тестирования обеспечивает:

-   ✅ **Прозрачность**: Четкая структура, понятные имена, документация
-   ✅ **Структурированность**: Логическая организация по модулям и типам
-   ✅ **Расширяемость**: Легко добавлять новые тесты и модули
-   ✅ **Модульность**: Независимый запуск тестов по модулям

Система готова к постепенному внедрению и масштабированию.
