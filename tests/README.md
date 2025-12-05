# Система тестирования stupidCMS

Структурированная система тестирования на базе Pest PHP для headless CMS.

## Установка завершена

✅ **Этап 1: Базовая настройка** — выполнен полностью

### Что сделано:

1. **Зависимости установлены:**

    - Pest PHP 3.0
    - Pest Plugin Laravel 3.0
    - Mockery 1.6

2. **Структура директорий создана:**

    - `tests/Unit/` — unit-тесты по доменным модулям
    - `tests/Feature/` — feature-тесты для HTTP и доменной логики
    - `tests/Helpers/` — вспомогательные классы и трейты
    - `tests/Modules/` — модульные конфигурации

3. **Конфигурация настроена:**

    - `phpunit.xml` — конфигурация PHPUnit/Pest
    - `tests/Pest.php` — главный конфиг Pest
    - `tests/Modules/*.php` — конфиги для 10 модулей

4. **Базовые классы созданы:**

    - `TestCase` — базовый класс для всех тестов
    - `CreatesApplication` — трейт для создания приложения
    - Трейты: `AuthenticatesUsers`, `CreatesMedia`, `CreatesEntries`, `MocksServices`

5. **Composer scripts добавлены:**
    - `composer test` — запуск всех тестов
    - `composer test:unit` — только unit-тесты
    - `composer test:feature` — только feature-тесты
    - `composer test:coverage` — с покрытием кода
    - `composer test:parallel` — параллельный запуск
    - `composer test:module:*` — по модулям (auth, media, entries и др.)

## Быстрый старт

### Запуск всех тестов

```bash
php artisan test
# или
composer test
```

### Запуск по типам

```bash
# Unit-тесты
composer test:unit

# Feature-тесты
composer test:feature
```

### Запуск по модулям

```bash
# Тесты модуля Media
composer test:module:media

# Тесты модуля Auth
composer test:module:auth

# Тесты модуля Entries
composer test:module:entries
```

### Покрытие кода

```bash
composer test:coverage
# или с минимальным порогом
php artisan test --coverage --min=80
```

### Параллельный запуск

```bash
composer test:parallel
```

## Структура проекта

```
tests/
├── Unit/                          # Unit-тесты
│   ├── Domain/                    # По доменным модулям
│   │   ├── Auth/
│   │   ├── Media/
│   │   ├── Entries/
│   │   ├── Options/
│   │   ├── Plugins/
│   │   ├── PostTypes/
│   │   ├── Routing/
│   │   ├── Sanitizer/
│   │   ├── Search/
│   │   └── View/
│   ├── Models/                    # Тесты моделей
│   ├── Rules/                     # Валидационные правила
│   └── Support/                   # Вспомогательные классы
│
├── Feature/                       # Feature-тесты
│   ├── Api/                       # HTTP API тесты
│   │   ├── Admin/V1/              # Админский API
│   │   └── Public/                # Публичный API
│   ├── Domain/                    # Доменная логика
│   └── Integration/               # Интеграционные тесты
│
├── Helpers/                       # Вспомогательные классы
│   ├── Traits/                    # Трейты для тестов
│   ├── Fixtures/                  # Тестовые данные
│   └── Factories/                 # Дополнительные фабрики
│
├── Modules/                       # Модульные конфигурации
│   ├── auth.php
│   ├── entries.php
│   ├── media.php
│   └── ...
│
├── Pest.php                       # Главная конфигурация Pest
├── TestCase.php                   # Базовый класс для тестов
├── CreatesApplication.php         # Трейт создания приложения
└── README.md                      # Этот файл
```

## Доступные модули

Система разделена на 10 модулей:

1. **auth** — Аутентификация и авторизация
2. **entries** — Управление записями контента
3. **media** — Управление медиа-файлами
4. **options** — Управление опциями системы
5. **plugins** — Система плагинов
6. **post-types** — Типы записей
7. **routing** — Резервирование путей и роутинг
8. **sanitizer** — Санитизация контента
9. **search** — Поиск и индексация
10. **view** — Рендеринг шаблонов

## Вспомогательные трейты

### AuthenticatesUsers

Упрощает аутентификацию в тестах:

```php
// Как администратор
$this->asAdmin()
    ->postJson('/api/v1/admin/entries', $data);

// Как обычный пользователь
$this->asUser()
    ->get('/api/v1/content');

// С JWT токеном
$this->withJwtToken($token)
    ->get('/api/v1/protected');
```

### CreatesMedia

Создание медиа-файлов:

```php
// Создать запись в БД
$media = $this->createMediaFile(['title' => 'Test Image']);

// Создать загружаемое изображение
$image = $this->createUploadedImage('photo.jpg', 1920, 1080);

// Создать PDF
$pdf = $this->createUploadedPdf('document.pdf', 500);

// Создать видео
$video = $this->createUploadedVideo('clip.mp4', 2048);
```

### CreatesEntries

Создание записей контента:

```php
// Обычная запись
$entry = $this->createEntry(['title' => 'Test']);

// Опубликованная
$entry = $this->createPublishedEntry();

// Черновик
$draft = $this->createDraftEntry();

// Тип записи
$postType = $this->createPostType(['name' => 'Article']);
```

### MocksServices

Создание моков:

```php
// Мок сервиса
$this->mockService(SomeService::class, function ($mock) {
    $mock->shouldReceive('process')
        ->once()
        ->andReturn('result');
});

// Spy (частичный мок)
$spy = $this->spyService(LogService::class);
```

## Написание тестов

### Пример Unit-теста

```php
<?php

declare(strict_types=1);

use App\Domain\Media\Services\ExifManager;

test('extracts EXIF data from image', function () {
    $manager = new ExifManager();
    $file = $this->createUploadedImage('photo.jpg');

    $exif = $manager->extract($file);

    expect($exif)
        ->toBeArray()
        ->toHaveKey('width')
        ->toHaveKey('height');
});
```

### Пример Feature-теста

```php
<?php

declare(strict_types=1);

test('admin can create media', function () {
    // Arrange
    $file = $this->createUploadedImage('test.jpg');

    // Act
    $response = $this->asAdmin()
        ->postJson('/api/v1/admin/media', [
            'file' => $file,
            'title' => 'Test Media',
        ]);

    // Assert
    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'title', 'url']]);

    $this->assertDatabaseHas('media', [
        'title' => 'Test Media',
    ]);
});
```

## Best Practices

### 1. Именование

-   Тесты: описательные, на английском: `test('admin can create entry', ...)`
-   Файлы: `*Test.php` (например, `MediaStoreActionTest.php`)
-   Группы: `module:media`, `api:admin`, `feature:upload`

### 2. Структура теста (Arrange-Act-Assert)

```php
test('description', function () {
    // Arrange (Given) - подготовка данных
    $user = User::factory()->create();

    // Act (When) - выполнение действия
    $response = $this->actingAs($user)->get('/endpoint');

    // Assert (Then) - проверка результата
    $response->assertStatus(200);
});
```

### 3. Изоляция

-   Каждый тест независим
-   Используйте `RefreshDatabase` для Feature-тестов
-   Очищайте данные после тестов
-   Мокайте внешние зависимости

### 4. Производительность

-   Unit-тесты без БД выполняются быстрее
-   Используйте параллельный запуск для больших наборов тестов
-   Оптимизируйте фабрики данных

## Следующие шаги

См. [docs/testing-system-plan.md](../docs/testing-system-plan.md) для продолжения внедрения:

-   **Этап 2:** Модульная конфигурация (завершено)
-   **Этап 3:** Примеры тестов для ключевых компонентов
-   **Этап 4:** Расширение покрытия
-   **Этап 5:** Автоматизация CI/CD

## Документация

-   [План системы тестирования](../docs/testing-system-plan.md)
-   [План тестирования сущностей](../docs/entities-testing-plan.md) ⭐ **NEW**
-   [Pest PHP](https://pestphp.com/docs)
-   [Laravel Testing](https://laravel.com/docs/12.x/testing)

---

**Дата создания:** 2025-01-17  
**Версия:** 1.0  
**Статус:** ✅ Базовая настройка завершена
