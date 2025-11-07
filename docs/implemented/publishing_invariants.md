# Инварианты публикации записей

## Обзор

Система обеспечивает жёсткие правила публикации записей (`entries`), гарантируя целостность данных и предотвращая рассинхрон между статусом и датой публикации. Все ошибки валидации возвращаются в формате **RFC 7807**.

## Правила

1. **Если `status = 'published'`**, тогда `published_at` **обязательно** должно быть `<= now()` (UTC).
2. **Если `status = 'draft'`**, значение `published_at` игнорируется (может быть `NULL` или любой датой, но не влияет на доступность записи).
3. **Автозаполнение**: система проставляет `now()` (UTC) **только** при:
   - Создании новой записи со статусом `published` без `published_at`
   - Переходе `draft → published` без указания `published_at`
   - Обновлении записи, у которой `published_at` ещё пустой
4. **Идемпотентность**: при обновлении уже опубликованной записи без указания `published_at` историческая дата сохраняется (не перезаписывается).
5. **Запрет будущих дат**: если `status='published'` и `published_at > now()` — ошибка `422` с полем `published_at`.
6. **Снятие публикации**: перевод `published → draft` разрешён без изменений `published_at`.

## Структура

```
app/
├── Support/Publishing/
│   └── PublishingService.php              # Доменный сервис для применения правил
├── Rules/
│   └── PublishedDateNotInFuture.php       # Правило валидации для FormRequest
└── Http/Requests/
    ├── StoreEntryRequest.php              # Валидация при создании
    └── UpdateEntryRequest.php             # Валидация при обновлении

bootstrap/
└── app.php                                # Обработчик исключений RFC 7807
```

## Использование

### PublishingService

Основной сервис для применения правил публикации и валидации инвариантов:

```php
use App\Support\Publishing\PublishingService;
use App\Models\Entry;

$publishingService = app(PublishingService::class);

// При создании записи
$payload = [
    'post_type_id' => 1,
    'title' => 'Новая запись',
    'slug' => 'novaya-zapis',
    'status' => 'published',
    'data_json' => [],
    // published_at не указан - будет автозаполнен
];

$processed = $publishingService->applyAndValidate($payload);
$entry = Entry::create($processed);

// При обновлении записи
$entry = Entry::find(1);
$payload = [
    'status' => 'published',
    'published_at' => '2024-01-01 12:00:00',
];

$processed = $publishingService->applyAndValidate($payload, $entry);
$entry->update($processed);
```

### FormRequest классы

Используйте готовые FormRequest классы в контроллерах:

```php
use App\Http\Requests\StoreEntryRequest;
use App\Http\Requests\UpdateEntryRequest;
use App\Support\Publishing\PublishingService;
use App\Models\Entry;

class EntryController extends Controller
{
    public function store(StoreEntryRequest $request, PublishingService $publishingService)
    {
        $payload = $request->validated();
        $processed = $publishingService->applyAndValidate($payload);
        $entry = Entry::create($processed);
        
        return response()->json($entry, 201);
    }

    public function update(UpdateEntryRequest $request, Entry $entry, PublishingService $publishingService)
    {
        $payload = $request->validated();
        $processed = $publishingService->applyAndValidate($payload, $entry);
        $entry->update($processed);
        
        return response()->json($entry);
    }
}
```

### Правило валидации PublishedDateNotInFuture

Можно использовать правило валидации напрямую:

```php
use App\Rules\PublishedDateNotInFuture;

$request->validate([
    'status' => 'required|in:draft,published',
    'published_at' => [
        'nullable',
        'date',
        new PublishedDateNotInFuture(),
    ],
]);
```

## Скоуп published()

Модель `Entry` содержит скоуп `published()` для выборки только опубликованных записей:

```php
use App\Models\Entry;

// Получить все опубликованные записи
$publishedEntries = Entry::published()->get();

// Опубликованные записи определённого типа
$publishedPages = Entry::published()
    ->ofType('page')
    ->get();

// Проверка, попадает ли запись в скоуп
$isPublished = Entry::published()->where('id', $entry->id)->exists();
```

**Важно**: Скоуп проверяет:
- `status = 'published'`
- `published_at IS NOT NULL`
- `published_at <= now()` (UTC)

## Формат ошибок (RFC 7807)

При нарушении правил публикации возвращается HTTP 422 в формате RFC 7807:

```json
{
  "type": "https://stupidcms.dev/problems/validation-error",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The given data was invalid.",
  "errors": {
    "published_at": ["Дата публикации не может быть в будущем для статуса \"published\""]
  }
}
```

### Примеры ошибок

**Попытка опубликовать с будущей датой:**

```http
POST /api/v1/admin/entries
Content-Type: application/json

{
  "post_type_id": 1,
  "title": "Запись",
  "slug": "zapis",
  "status": "published",
  "published_at": "2025-12-31 23:59:59",
  "data_json": {}
}
```

**Ответ:**

```http
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/json

{
  "type": "https://stupidcms.dev/problems/validation-error",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The given data was invalid.",
  "errors": {
    "published_at": ["Дата публикации не может быть в будущем для статуса \"published\""]
  }
}
```

## Сценарии использования

### 1. Создание черновика

```php
$payload = [
    'post_type_id' => 1,
    'title' => 'Черновик',
    'slug' => 'chernovik',
    'status' => 'draft',
    'published_at' => null, // или любая дата - игнорируется
    'data_json' => [],
];

$processed = $publishingService->applyAndValidate($payload);
$entry = Entry::create($processed);
// ✅ Успешно: draft может иметь любую дату или null
```

### 2. Публикация без даты (автозаполнение)

```php
$payload = [
    'post_type_id' => 1,
    'title' => 'Публикация',
    'slug' => 'publikatsiya',
    'status' => 'published',
    // published_at не указан
    'data_json' => [],
];

$processed = $publishingService->applyAndValidate($payload);
// ✅ published_at автоматически заполнен текущим временем (UTC)
$entry = Entry::create($processed);
```

### 3. Публикация с прошедшей датой

```php
$payload = [
    'post_type_id' => 1,
    'title' => 'Прошлое',
    'slug' => 'proshloe',
    'status' => 'published',
    'published_at' => '2024-01-01 12:00:00', // Прошедшая дата
    'data_json' => [],
];

$processed = $publishingService->applyAndValidate($payload);
$entry = Entry::create($processed);
// ✅ Успешно: прошедшая дата разрешена
```

### 4. Публикация с будущей датой (ошибка)

```php
$payload = [
    'post_type_id' => 1,
    'title' => 'Будущее',
    'slug' => 'budushchee',
    'status' => 'published',
    'published_at' => '2025-12-31 23:59:59', // Будущая дата
    'data_json' => [],
];

try {
    $processed = $publishingService->applyAndValidate($payload);
} catch (\Illuminate\Validation\ValidationException $e) {
    // ❌ ValidationException с ошибкой published_at
}
```

### 5. Обновление опубликованной записи без изменения даты

```php
$entry = Entry::find(1); // status = 'published', published_at = '2024-01-01'

$payload = [
    'title' => 'Обновлённый заголовок',
    // status и published_at не указаны
];

$processed = $publishingService->applyAndValidate($payload, $entry);
$entry->update($processed);
// ✅ Успешно: дата не изменяется
```

### 6. Обновление опубликованной записи с переводом даты в будущее (ошибка)

```php
$entry = Entry::find(1); // status = 'published', published_at = '2024-01-01'

$payload = [
    'status' => 'published',
    'published_at' => '2025-12-31 23:59:59', // Будущая дата
];

try {
    $processed = $publishingService->applyAndValidate($payload, $entry);
} catch (\Illuminate\Validation\ValidationException $e) {
    // ❌ ValidationException
}
```

### 7. Снятие публикации (unpublish)

```php
$entry = Entry::find(1); // status = 'published'

$payload = [
    'status' => 'draft',
    // published_at может остаться прежним или быть любым
];

$processed = $publishingService->applyAndValidate($payload, $entry);
$entry->update($processed);
// ✅ Успешно: draft может иметь любую дату
// Запись больше не попадает в Entry::published()
```

## Временные зоны

- **Хранение**: все даты хранятся в **UTC**
- **Серверное время**: `Carbon::now('UTC')` используется везде
- **UI**: преобразования в часовой пояс пользователя выполняются на уровне фронтенда

## Интеграция с EntryObserver

`PublishingService` вызывается **до** сохранения модели в контроллере или Application Service. Он не интегрирован напрямую с `EntryObserver`, чтобы сохранить гибкость использования.

## Тестирование

### Unit тесты

- `tests/Unit/PublishingServiceTest.php` — тесты сервиса (13 тестов)
  - Базовые сценарии публикации
  - Идемпотентность обновлений
  - Переходы draft → published
  - Граничные случаи (boundary now)
- `tests/Unit/PublishedDateNotInFutureRuleTest.php` — тесты правила валидации (7 тестов)

### Feature тесты

- `tests/Feature/PublishingInvariantsTest.php` — интеграционные тесты (11 тестов)
  - Полный цикл создания/обновления записей
  - Проверка скоупа `published()`
  - Исключения из выборки

**Всего: 31 тест, все проходят ✅**

**Примечание**: Все тесты используют `Carbon::setTestNow()` для стабильности и исключения флаков на границе секунд.

### Запуск тестов

```bash
# Все тесты публикации
php artisan test --filter=Publishing

# Только unit тесты
php artisan test --filter=PublishingServiceTest
php artisan test --filter=PublishedDateNotInFutureRuleTest

# Только feature тесты
php artisan test --filter=PublishingInvariantsTest
```

## Ограничения и будущие улучшения

### Текущие ограничения

- Отложенные публикации не поддерживаются (статус `scheduled` отсутствует)
- Временная зона по сайту не настроена (всегда UTC)

### Планируемые улучшения (out of scope)

- Статус `scheduled` и планировщик публикаций
- Временная зона по сайту (site-wide TZ)
- Массовая публикация/снятие публикации

## Связанные компоненты

- **Entry модель**: содержит скоуп `published()` и каст `published_at` в datetime
- **EntryObserver**: обрабатывает slug и другие аспекты жизненного цикла Entry
- **FormRequest валидация**: базовая валидация типов и правил

## Миграции

Таблица `entries` уже содержит необходимые поля:
- `status ENUM('draft','published')` — статус записи
- `published_at DATETIME NULL` — дата публикации (UTC) с индексом

### Дополнительная миграция

`2025_11_06_000025_add_publishing_index_to_entries_table.php` — добавляет составной индекс `(status, published_at)` для оптимизации запросов `scopePublished()`. Этот индекс значительно ускоряет выборки опубликованных записей.

