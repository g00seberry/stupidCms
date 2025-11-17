# Документация по тестированию

## Структура тестов

Проект использует модульную организацию тестов с разделением на Unit и Feature тесты.

### Базовые классы

#### `Tests\Support\BaseTestCase`

Базовый класс для всех тестов. Расширяет `Tests\TestCase` и предоставляет общую функциональность.

#### `Tests\Support\FeatureTestCase`

Базовый класс для Feature тестов. Включает:

-   `RefreshDatabase` - полная пересборка БД между тестами
-   `HasAdminUser` - методы для создания административных пользователей

**Использование:**

```php
use Tests\Support\FeatureTestCase;

class MyFeatureTest extends FeatureTestCase
{
    public function test_something(): void
    {
        $admin = $this->admin(['permission1', 'permission2']);
        // или
        $admin = $this->adminWithPermissions(['permission1', 'permission2']);
    }
}
```

#### `Tests\Support\MediaTestCase`

Базовый класс для тестов Media. Включает все возможности `FeatureTestCase` плюс:

-   Автоматическую настройку конфигурации Media (`media.disks`, `media.allowed_mimes`)
-   Автоматическую настройку фейкового хранилища (`Storage::fake('media')`)

**Использование:**

```php
use Tests\Support\MediaTestCase;

class MyMediaTest extends MediaTestCase
{
    public function test_media_upload(): void
    {
        // Storage::fake('media') уже настроен
        // Конфигурация Media уже настроена
        $admin = $this->admin(['media.create']);
        // ...
    }
}
```

### Трейты

#### `Tests\Support\Concerns\HasAdminUser`

Предоставляет методы для создания административных пользователей:

-   `admin(array $permissions = []): User` - создает админа с указанными разрешениями
-   `adminWithPermissions(array $permissions): User` - создает админа и выдает разрешения через `grantAdminPermissions()`

#### `Tests\Support\Concerns\ConfiguresMedia`

Предоставляет метод для настройки конфигурации Media:

-   `configureMediaDefaults(): void` - устанавливает стандартные значения конфигурации

#### `Tests\Support\Concerns\UsesFakeStorage`

Предоставляет метод для настройки фейкового хранилища:

-   `setUpFakeStorage(string $disk = 'media'): void` - настраивает `Storage::fake()` для указанного диска

### Helpers

#### `Tests\Support\Helpers\TestDataFactory`

Фабрика для создания тестовых данных:

-   `createMedia(array $attributes = []): Media`
-   `createEntry(array $attributes = []): Entry`

## Модули тестов

Тесты организованы по модулям в `phpunit.xml`:

### Media

-   `tests/Feature/Admin/Media/` - Feature тесты Media
-   `tests/Unit/Domain/Media/` - Unit тесты доменной логики Media
-   `tests/Unit/Media/` - Unit тесты компонентов Media

**Запуск:**

```bash
php artisan test --testsuite=Media
```

### Entries

-   `tests/Feature/Admin/Entries/` - Feature тесты Entries

**Запуск:**

```bash
php artisan test --testsuite=Entries
```

### Auth

-   `tests/Feature/Auth*.php` - Feature тесты аутентификации

**Запуск:**

```bash
php artisan test --testsuite=Auth
```

### Search

-   `tests/Feature/Admin/Search/` - Feature тесты админского поиска
-   `tests/Feature/Search/` - Feature тесты публичного поиска
-   `tests/Unit/Search/` - Unit тесты поиска

**Запуск:**

```bash
php artisan test --testsuite=Search
```

## Написание новых тестов

### Feature тесты

Для Feature тестов используйте `FeatureTestCase`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\MyFeature;

use Tests\Support\FeatureTestCase;

class MyFeatureTest extends FeatureTestCase
{
    public function test_something(): void
    {
        $admin = $this->admin(['my.permission']);
        // тест...
    }
}
```

### Media тесты

Для тестов Media используйте `MediaTestCase`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use Tests\Support\MediaTestCase;

class MyMediaTest extends MediaTestCase
{
    public function test_media_operation(): void
    {
        // Storage и конфигурация уже настроены
        $admin = $this->admin(['media.create']);
        // тест...
    }
}
```

### Unit тесты

Для Unit тестов используйте `Tests\TestCase` напрямую или создайте специализированный базовый класс:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\MyUnit;

use Tests\TestCase;

class MyUnitTest extends TestCase
{
    public function test_something(): void
    {
        // тест без БД...
    }
}
```

## Оптимизация производительности

### DatabaseTransactions vs RefreshDatabase

-   **Unit тесты с БД** используют `DatabaseTransactions` - быстрее, так как откатывает транзакции вместо полной пересборки БД
-   **Feature тесты** используют `RefreshDatabase` через `FeatureTestCase` - полная пересборка БД для изоляции

**Преимущества DatabaseTransactions:**

-   Быстрее выполнение (откат транзакций вместо пересборки схемы)
-   Меньше нагрузки на БД
-   Подходит для Unit тестов, где не нужна полная изоляция

**Когда использовать RefreshDatabase:**

-   Feature тесты, требующие полной изоляции
-   Тесты, которые изменяют структуру БД (миграции, индексы)

## Рекомендации

1. **Всегда используйте `declare(strict_types=1);`** в начале файлов
2. **Используйте базовые классы** вместо дублирования кода
3. **Используйте трейты** для переиспользования логики
4. **Документируйте тесты** - добавляйте PHPDoc к методам
5. **Следуйте PSR-12** стандарту кодирования
6. **Unit тесты с БД** - используйте `DatabaseTransactions`
7. **Feature тесты** - используйте `FeatureTestCase` (включает `RefreshDatabase`)

## Миграция существующих тестов

При миграции существующих тестов:

1. Замените `use RefreshDatabase;` на наследование от `FeatureTestCase`
2. Удалите дублирующиеся методы `admin()` и `adminWithPermissions()`
3. Для Media тестов используйте `MediaTestCase` вместо `FeatureTestCase`
4. Удалите дублирующиеся вызовы `Storage::fake()` и конфигурацию, если они уже есть в базовом классе

## Примеры

### Создание админа с разрешениями

```php
// Вариант 1: через admin()
$admin = $this->admin(['media.create', 'media.read']);

// Вариант 2: через adminWithPermissions()
$admin = $this->adminWithPermissions(['media.create', 'media.read']);
```

### Настройка конфигурации Media (только если нужно переопределить)

```php
protected function setUp(): void
{
    parent::setUp();
    // Переопределяем конфигурацию, если нужно
    config()->set('media.variants', [
        'thumbnail' => ['max' => 320],
    ]);
}
```

### Использование TestDataFactory

```php
use Tests\Support\Helpers\TestDataFactory;

$media = TestDataFactory::createMedia(['title' => 'Test']);
$entry = TestDataFactory::createEntry(['title' => 'Test']);
```
