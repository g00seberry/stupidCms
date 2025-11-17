# План миграции тестовой системы

## Фаза 1: Критические исправления (1-2 дня)

### Задача 1.1: Исправить phpunit.xml

**Проблема:** Дублирование тестов в test suites (47 warning при запуске).

**Решение:**

```xml
<!-- БЫЛО -->
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
    <testsuite name="Media">
        <directory>tests/Feature/Admin/Media</directory>  <!-- Дублирование! -->
        <directory>tests/Unit/Domain/Media</directory>
    </testsuite>
</testsuites>

<!-- ДОЛЖНО БЫТЬ -->
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
        <exclude>tests/Unit/Domain/Media</exclude>
        <exclude>tests/Unit/Media</exclude>
        <exclude>tests/Unit/Search</exclude>
    </testsuite>
    
    <testsuite name="Integration">
        <directory>tests/Integration</directory>
    </testsuite>
    
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
        <exclude>tests/Feature/Admin/Media</exclude>
        <exclude>tests/Feature/Admin/Entries</exclude>
        <exclude>tests/Feature/Admin/Search</exclude>
        <exclude>tests/Feature/Search</exclude>
        <exclude>tests/Feature/Auth*.php</exclude>
        <exclude>tests/Feature/JwtAuthTest.php</exclude>
    </testsuite>
    
    <!-- Доменные test suites БЕЗ дублирования -->
    <testsuite name="Media">
        <directory>tests/Feature/Admin/Media</directory>
        <directory>tests/Unit/Domain/Media</directory>
        <directory>tests/Unit/Media</directory>
        <directory>tests/Integration/Domain/Media</directory>
    </testsuite>
    
    <testsuite name="Auth">
        <file>tests/Feature/AuthCsrfTest.php</file>
        <file>tests/Feature/AuthCurrentUserTest.php</file>
        <file>tests/Feature/AuthLoginTest.php</file>
        <file>tests/Feature/AuthLogoutTest.php</file>
        <file>tests/Feature/AuthRefreshTest.php</file>
        <file>tests/Feature/AuthorizationTest.php</file>
        <file>tests/Feature/JwtAuthTest.php</file>
        <directory>tests/Integration/Auth</directory>
    </testsuite>
    
    <testsuite name="Entries">
        <directory>tests/Feature/Admin/Entries</directory>
        <directory>tests/Integration/Domain/Entries</directory>
    </testsuite>
    
    <testsuite name="Search">
        <directory>tests/Feature/Admin/Search</directory>
        <directory>tests/Feature/Search</directory>
        <directory>tests/Unit/Search</directory>
        <directory>tests/Integration/Search</directory>
    </testsuite>
</testsuites>
```

**Команды:**

```bash
# 1. Создать директорию Integration
mkdir -p tests/Integration/Domain/Media
mkdir -p tests/Integration/Domain/Entries
mkdir -p tests/Integration/Auth
mkdir -p tests/Integration/Search

# 2. Обновить phpunit.xml (см. выше)

# 3. Проверить, что warning исчезли
php artisan test --list-tests 2>&1 | grep "Cannot add"
```

---

### Задача 1.2: Переключить SQLite на in-memory

**Проблема:** SQLite на диске медленнее на 20-30%.

**Решение:**

```xml
<!-- phpunit.xml -->
<env name="DB_DATABASE" value=":memory:"/>
```

**Метрики:**
- До: ~45 секунд для всех тестов
- После: ~30-35 секунд

---

### Задача 1.3: Создать IntegrationTestCase

**Файл:** `tests/Support/IntegrationTestCase.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Базовый класс для Integration тестов.
 *
 * Включает:
 * - DatabaseTransactions для быстрого отката изменений
 * - Методы для работы с доменной логикой без HTTP слоя
 */
abstract class IntegrationTestCase extends BaseTestCase
{
    use DatabaseTransactions;
}
```

---

### Задача 1.4: Переместить интеграционные тесты из Unit/ в Integration/

**Критерий:** Если тест использует БД (`RefreshDatabase` или `DatabaseTransactions`), но не тестирует HTTP — это Integration тест.

**Файлы для переноса:**

```bash
# Переместить
tests/Unit/Domain/Media/Actions/MediaStoreActionTest.php
  → tests/Integration/Domain/Media/Actions/MediaStoreActionTest.php

tests/Unit/Domain/Media/Actions/UpdateMediaMetadataActionTest.php
  → tests/Integration/Domain/Media/Actions/UpdateMediaMetadataActionTest.php

tests/Unit/Domain/Media/Actions/ListMediaActionTest.php
  → tests/Integration/Domain/Media/Actions/ListMediaActionTest.php

tests/Unit/Domain/Media/MediaRepositoryTest.php
  → tests/Integration/Domain/Media/MediaRepositoryTest.php

tests/Unit/Domain/Media/OnDemandVariantServiceTest.php
  → tests/Integration/Domain/Media/OnDemandVariantServiceTest.php
```

**Скрипт миграции:**

```bash
#!/bin/bash
# scripts/migrate-integration-tests.sh

mkdir -p tests/Integration/Domain/Media/Actions
mkdir -p tests/Integration/Domain/Media

# Media Actions
mv tests/Unit/Domain/Media/Actions/MediaStoreActionTest.php \
   tests/Integration/Domain/Media/Actions/MediaStoreActionTest.php

mv tests/Unit/Domain/Media/Actions/UpdateMediaMetadataActionTest.php \
   tests/Integration/Domain/Media/Actions/UpdateMediaMetadataActionTest.php

mv tests/Unit/Domain/Media/Actions/ListMediaActionTest.php \
   tests/Integration/Domain/Media/Actions/ListMediaActionTest.php

# Media Services
mv tests/Unit/Domain/Media/MediaRepositoryTest.php \
   tests/Integration/Domain/Media/MediaRepositoryTest.php

mv tests/Unit/Domain/Media/OnDemandVariantServiceTest.php \
   tests/Integration/Domain/Media/OnDemandVariantServiceTest.php

echo "✅ Тесты перемещены в Integration/"
echo "⚠️  Не забудьте обновить namespace в файлах!"
```

**Обновление namespace:**

```php
// БЫЛО
namespace Tests\Unit\Domain\Media\Actions;

// СТАЛО
namespace Tests\Integration\Domain\Media\Actions;
```

---

### Задача 1.5: Заменить RefreshDatabase на DatabaseTransactions в Integration тестах

```php
// tests/Integration/Domain/Media/Actions/MediaStoreActionTest.php

// БЫЛО:
use Illuminate\Foundation\Testing\RefreshDatabase;

final class MediaStoreActionTest extends TestCase
{
    use RefreshDatabase;  // ❌ Медленно

// СТАЛО:
use Tests\Support\IntegrationTestCase;

final class MediaStoreActionTest extends IntegrationTestCase  // ✅ Уже включает DatabaseTransactions
{
    // use DatabaseTransactions; - уже в IntegrationTestCase
```

**Метрики:**
- До: ~150ms на тест
- После: ~15ms на тест
- **Ускорение: 10x** для ~40 тестов

---

## Фаза 2: Создание базовых классов для переиспользования (2-3 дня)

### Задача 2.1: MediaActionTestCase

**Файл:** `tests/Support/MediaActionTestCase.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Media\Services\CollectionRulesResolver;
use App\Domain\Media\Services\ExifManager;
use App\Domain\Media\Services\MediaMetadataExtractor;
use App\Domain\Media\Services\StorageResolver;
use App\Domain\Media\Validation\MediaValidationPipeline;
use Illuminate\Support\Facades\Storage;
use Mockery;

/**
 * Базовый класс для тестов Media Actions.
 *
 * Предоставляет:
 * - Готовые моки всех зависимостей Actions
 * - Автоматическую настройку Storage::fake()
 * - Автоматическую конфигурацию Media
 */
abstract class MediaActionTestCase extends IntegrationTestCase
{
    use Concerns\ConfiguresMedia;

    protected MediaMetadataExtractor $metadataExtractor;
    protected StorageResolver $storageResolver;
    protected CollectionRulesResolver $collectionRulesResolver;
    protected MediaValidationPipeline $validationPipeline;
    protected ?ExifManager $exifManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configureMediaDefaults();
        Storage::fake('media');
        
        $this->setUpMediaMocks();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Инициализировать моки для Media сервисов.
     */
    protected function setUpMediaMocks(): void
    {
        $this->metadataExtractor = Mockery::mock(MediaMetadataExtractor::class);
        $this->storageResolver = Mockery::mock(StorageResolver::class);
        $this->collectionRulesResolver = Mockery::mock(CollectionRulesResolver::class);
        $this->validationPipeline = Mockery::mock(MediaValidationPipeline::class);
        $this->exifManager = null;
    }

    /**
     * Настроить стандартное поведение моков для успешного сценария.
     */
    protected function mockSuccessfulUpload(): void
    {
        $this->validationPipeline->shouldReceive('validate')->byDefault();
        $this->collectionRulesResolver->shouldReceive('getRules')->andReturn([])->byDefault();
        $this->storageResolver->shouldReceive('resolveDiskName')->andReturn('media')->byDefault();
    }
}
```

**Использование:**

```php
// tests/Integration/Domain/Media/Actions/MediaStoreActionTest.php

use Tests\Support\MediaActionTestCase;

final class MediaStoreActionTest extends MediaActionTestCase
{
    // setUp() уже настроил все моки и Storage
    
    public function test_stores_file_with_by_date_path_strategy(): void
    {
        $this->mockSuccessfulUpload();  // ✅ Один вызов вместо 10 строк
        
        // ... тест
    }
}
```

---

### Задача 2.2: Исправить Feature тесты для использования FeatureTestCase

**Проблемные файлы:**

```bash
# Feature тесты, наследующие напрямую от TestCase
tests/Feature/Admin/PostTypes/StorePostTypeTest.php
tests/Feature/Admin/PostTypes/UpdatePostTypeTest.php
tests/Feature/Admin/PostTypes/DeletePostTypeTest.php
# ... ~20 файлов
```

**Скрипт поиска:**

```bash
grep -r "extends TestCase" tests/Feature/ | grep -v "FeatureTestCase"
```

**Исправление:**

```php
// БЫЛО:
namespace Tests\Feature\Admin\PostTypes;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StorePostTypeTest extends TestCase
{
    use RefreshDatabase;

// СТАЛО:
namespace Tests\Feature\Admin\PostTypes;

use Tests\Support\FeatureTestCase;

class StorePostTypeTest extends FeatureTestCase
{
    // RefreshDatabase уже включен в FeatureTestCase
```

**Скрипт автоматической замены:**

```bash
#!/bin/bash
# scripts/fix-feature-tests-inheritance.sh

for file in $(grep -rl "extends TestCase" tests/Feature/ | grep -v "FeatureTestCase"); do
    echo "Fixing: $file"
    
    # Заменить use TestCase на use FeatureTestCase
    sed -i 's/use Tests\\TestCase;/use Tests\\Support\\FeatureTestCase;/g' "$file"
    
    # Заменить extends TestCase на extends FeatureTestCase
    sed -i 's/extends TestCase/extends FeatureTestCase/g' "$file"
    
    # Удалить use RefreshDatabase (уже в FeatureTestCase)
    sed -i '/use RefreshDatabase;/d' "$file"
done

echo "✅ Feature тесты исправлены"
```

---

### Задача 2.3: Заменить User::factory() на $this->admin()

**Проблема:**

```php
// tests/Feature/Admin/PostTypes/StorePostTypeTest.php:21
$admin = User::factory()->create(['is_admin' => true]);

// Должно быть:
$admin = $this->admin(['manage.posttypes']);
```

**Скрипт поиска:**

```bash
grep -r "User::factory()->create" tests/Feature/
```

**Скрипт замены:**

```bash
#!/bin/bash
# scripts/replace-user-factory-with-admin-helper.sh

for file in $(grep -rl "User::factory()->create" tests/Feature/); do
    echo "Processing: $file"
    
    # Заменить User::factory()->create(['is_admin' => true]) на $this->admin()
    sed -i "s/User::factory()->create(\['is_admin' => true\])/$this->admin()/g" "$file"
    
    # Удалить use App\Models\User если он больше не нужен
    # (оставить только если User используется в других местах)
done

echo "✅ User::factory() заменены на \$this->admin()"
```

---

## Фаза 3: Улучшение качества тестов (3-4 дня)

### Задача 3.1: Реализовать пропущенные тесты

**Файлы с markTestSkipped:**

```bash
grep -r "markTestSkipped" tests/
```

**Найдено:**

1. `tests/Unit/Domain/Media/Actions/MediaStoreActionTest.php:120`
   ```php
   public function test_handles_file_storage_failure(): void
   {
       $this->markTestSkipped('Requires Filesystem mock...');
   }
   ```

   **Решение:** Использовать мок Storage через Mockery.

2. `tests/Feature/Admin/Media/MediaApiTest.php:527`
   ```php
   public function test_it_handles_avif_image_format(): void
   {
       $this->markTestSkipped('AVIF test requires a real AVIF file...');
   }
   ```

   **Решение:** Добавить реальный AVIF файл в `tests/Feature/Admin/Media/fixtures/`.

3. `tests/Feature/Admin/Media/MediaApiTest.php:815`
   ```php
   public function test_store_handles_storage_failure(): void
   {
       $this->markTestSkipped('Requires complex Storage mocking...');
   }
   ```

   **Решение:** Использовать Mockery для мока Storage facade.

---

### Задача 3.2: Внедрить Data Providers

**Проблема:** Дублирование тестов валидации.

**Пример:**

```php
// БЫЛО (4 теста):
public function test_it_validates_title_min_length(): void { ... }
public function test_it_validates_alt_min_length(): void { ... }
public function test_it_validates_title_max_length(): void { ... }
public function test_it_validates_alt_max_length(): void { ... }

// ДОЛЖНО БЫТЬ (1 тест с data provider):
/**
 * @dataProvider validationFieldsProvider
 */
public function test_validates_field_length(string $field, string $value, bool $shouldFail): void
{
    $admin = $this->admin(['media.create']);
    $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

    $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [
        $field => $value,
    ], ['file' => $file], $admin);

    if ($shouldFail) {
        $response->assertStatus(422);
        $this->assertValidationErrors($response, [$field]);
    } else {
        $response->assertCreated();
    }
}

public static function validationFieldsProvider(): array
{
    return [
        'title too short' => ['title', '', true],
        'title too long' => ['title', str_repeat('a', 256), true],
        'title valid' => ['title', 'Valid Title', false],
        'alt too short' => ['alt', '', true],
        'alt too long' => ['alt', str_repeat('a', 256), true],
        'alt valid' => ['alt', 'Valid Alt', false],
    ];
}
```

**Метрики:**
- До: 4 теста, ~200 строк кода
- После: 1 тест, ~50 строк кода
- Экономия: **75% кода**

---

### Задача 3.3: Переработать TestDataFactory в Builder

**Текущая проблема:** `TestDataFactory` не используется (мёртвый код).

**Решение:** Создать MediaBuilder с fluent API.

**Файл:** `tests/Support/Builders/MediaBuilder.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Support\Builders;

use App\Models\Media;
use Illuminate\Http\UploadedFile;

/**
 * Builder для создания Media в тестах с fluent API.
 */
class MediaBuilder
{
    private array $attributes = [];
    private ?UploadedFile $file = null;

    public static function new(): self
    {
        return new self();
    }

    public function asImage(): self
    {
        $this->attributes['mime'] = 'image/jpeg';
        $this->attributes['ext'] = 'jpg';
        $this->attributes['kind'] = 'image';
        return $this;
    }

    public function asVideo(): self
    {
        $this->attributes['mime'] = 'video/mp4';
        $this->attributes['ext'] = 'mp4';
        $this->attributes['kind'] = 'video';
        return $this;
    }

    public function asAudio(): self
    {
        $this->attributes['mime'] = 'audio/mpeg';
        $this->attributes['ext'] = 'mp3';
        $this->attributes['kind'] = 'audio';
        return $this;
    }

    public function inCollection(string $collection): self
    {
        $this->attributes['collection'] = $collection;
        return $this;
    }

    public function withTitle(string $title): self
    {
        $this->attributes['title'] = $title;
        return $this;
    }

    public function withDimensions(int $width, int $height): self
    {
        $this->attributes['width'] = $width;
        $this->attributes['height'] = $height;
        return $this;
    }

    public function withFile(UploadedFile $file): self
    {
        $this->file = $file;
        return $this;
    }

    public function deleted(): self
    {
        $this->attributes['deleted_at'] = now();
        return $this;
    }

    public function create(): Media
    {
        return Media::factory()->create($this->attributes);
    }

    public function make(): Media
    {
        return Media::factory()->make($this->attributes);
    }
}
```

**Использование:**

```php
// БЫЛО:
$media = Media::factory()->create([
    'mime' => 'image/jpeg',
    'ext' => 'jpg',
    'collection' => 'banners',
    'title' => 'Hero Image',
    'width' => 1920,
    'height' => 1080,
]);

// СТАЛО:
$media = MediaBuilder::new()
    ->asImage()
    ->inCollection('banners')
    ->withTitle('Hero Image')
    ->withDimensions(1920, 1080)
    ->create();
```

**Преимущества:**
- Читаемость +++
- Переиспользование логики
- Легко добавлять новые методы (например, `->withExif()`, `->onDisk('s3')`)

---

## Фаза 4: Архитектурные улучшения (опционально, 5+ дней)

### Задача 4.1: Внедрить Parallel Testing

**Файл:** `phpunit.xml`

```xml
<extensions>
    <bootstrap class="Illuminate\Testing\ParallelTestingExtension"/>
</extensions>
```

**Конфигурация:** `.env.testing`

```env
PARATEST_PROCESSES=4  # Количество процессов
```

**Метрики:**
- До: 45 секунд
- После (4 процесса): ~15 секунд
- **Ускорение: 3x**

---

### Задача 4.2: Добавить Coverage Gates

**Файл:** `phpunit.xml`

```xml
<coverage includeUncoveredFiles="true">
    <report>
        <html outputDirectory="coverage"/>
        <clover outputFile="coverage/clover.xml"/>
    </report>
</coverage>
```

**CI/CD (GitHub Actions):**

```yaml
# .github/workflows/tests.yml
- name: Run tests with coverage
  run: php artisan test --coverage --min=80

- name: Upload coverage
  uses: codecov/codecov-action@v3
  with:
    files: ./coverage/clover.xml
```

---

### Задача 4.3: Contract Testing для API

**Файл:** `tests/Contracts/MediaApiContractTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Contracts;

use Tests\Support\FeatureTestCase;

/**
 * Contract тесты для Media API.
 *
 * Проверяют соответствие API документации (OpenAPI schema).
 */
class MediaApiContractTest extends FeatureTestCase
{
    public function test_store_media_matches_openapi_schema(): void
    {
        $admin = $this->admin(['media.create']);
        
        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [], [
            'file' => UploadedFile::fake()->image('test.jpg'),
        ], $admin);

        $response->assertCreated();
        
        // Валидация против OpenAPI схемы
        $this->assertMatchesOpenApiSchema($response, 'MediaResource', 201);
    }
}
```

---

## Метрики улучшений

### Производительность

| Метрика | До | После | Улучшение |
|---------|----|----|-----------|
| Время выполнения всех тестов | 45 сек | 15 сек | **3x** |
| Время Unit тестов | 8 сек | 1 сек | **8x** |
| Время Integration тестов | 15 сек | 5 сек | **3x** |
| Время Feature тестов | 22 сек | 9 сек | **2.4x** |

### Качество

| Метрика | До | После | Улучшение |
|---------|----|----|-----------|
| Покрытие кода | ~70% | ~85% | +15% |
| Дублирующийся код в тестах | ~400 строк | ~50 строк | **-88%** |
| Пропущенные тесты | 5 | 0 | **100%** |
| Предупреждения phpunit | 47 | 0 | **100%** |

---

## Чек-лист выполнения

### Фаза 1 (критично)
- [ ] Исправить phpunit.xml (убрать дублирование)
- [ ] Переключить SQLite на `:memory:`
- [ ] Создать `IntegrationTestCase`
- [ ] Переместить тесты из `Unit/` в `Integration/`
- [ ] Заменить `RefreshDatabase` на `DatabaseTransactions` в Integration

### Фаза 2 (важно)
- [ ] Создать `MediaActionTestCase`
- [ ] Исправить Feature тесты для использования `FeatureTestCase`
- [ ] Заменить `User::factory()` на `$this->admin()`

### Фаза 3 (качество)
- [ ] Реализовать пропущенные тесты (5 шт.)
- [ ] Внедрить Data Providers
- [ ] Переработать `TestDataFactory` в `MediaBuilder`

### Фаза 4 (опционально)
- [ ] Внедрить Parallel Testing
- [ ] Добавить Coverage Gates
- [ ] Contract Testing для API

---

## Запуск миграции

### Последовательность команд

```bash
# 1. Фаза 1
bash scripts/migrate-integration-tests.sh
# Вручную обновить namespace в перемещённых файлах
# Обновить phpunit.xml (см. Задача 1.1)

# 2. Запустить тесты
php artisan test

# 3. Проверить отсутствие warning
php artisan test --list-tests 2>&1 | grep "Cannot add"

# 4. Фаза 2
bash scripts/fix-feature-tests-inheritance.sh
bash scripts/replace-user-factory-with-admin-helper.sh

# 5. Фаза 3
# Реализовать пропущенные тесты вручную
# Внедрить Data Providers
# Создать MediaBuilder

# 6. Запустить финальную проверку
php artisan test --coverage

# 7. Зафиксировать изменения
git add .
git commit -m "Refactor test architecture: fix performance, remove duplication, add Integration layer"
```

---

## Риски и mitigation

### Риск 1: Тесты сломаются при миграции

**Mitigation:**
- Мигрировать по одному модулю (Media → Entries → Search → ...)
- Запускать тесты после каждого шага
- Использовать Git для отката в случае проблем

### Риск 2: DatabaseTransactions могут вызвать проблемы с вложенными транзакциями

**Mitigation:**
- Проверить, что тестируемый код не использует вложенные транзакции
- Если проблема возникает, вернуться к `RefreshDatabase` для конкретного теста

### Риск 3: Parallel Testing может выявить race conditions

**Mitigation:**
- Внедрять поэтапно (сначала на CI, потом локально)
- Использовать отдельные БД для каждого процесса

---

## Поддержка после миграции

### Обновление документации

- [ ] Обновить `tests/README.md`
- [ ] Создать `docs/testing/ARCHITECTURE.md`
- [ ] Создать `docs/testing/BEST_PRACTICES.md`

### Обучение команды

- [ ] Провести code review новой структуры
- [ ] Добавить примеры в документацию
- [ ] Создать чек-лист для написания новых тестов

---

## Заключение

Миграция улучшит:
- **Производительность:** 3x ускорение
- **Качество:** +15% покрытие, 0 пропущенных тестов
- **Поддерживаемость:** -88% дублирующегося кода

Критичные фазы 1-2 можно выполнить за 2-3 дня, качество (фаза 3) — ещё 3-4 дня.

