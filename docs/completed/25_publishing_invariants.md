# Задача 25. Инварианты публикации — выполнено ✅

## Резюме

Реализованы жёсткие правила публикации записей (`entries`) с автоматическим заполнением даты публикации и валидацией инвариантов. Все ошибки возвращаются в формате RFC 7807.

## Реализованные компоненты

### 1. PublishingService
- **Файл**: `app/Support/Publishing/PublishingService.php`
- **Функционал**:
  - Автозаполнение `published_at` при публикации
  - Валидация инварианта: запрет будущих дат для статуса `published`
  - Поддержка создания и обновления записей

### 2. Правило валидации
- **Файл**: `app/Rules/PublishedDateNotInFuture.php`
- **Использование**: В FormRequest классах для валидации `published_at`

### 3. FormRequest классы
- **Файлы**: 
  - `app/Http/Requests/StoreEntryRequest.php`
  - `app/Http/Requests/UpdateEntryRequest.php`
- **Функционал**: Базовая валидация типов + правило `PublishedDateNotInFuture`

### 4. Обновление модели Entry
- **Файл**: `app/Models/Entry.php`
- **Изменение**: `scopePublished()` использует `Carbon::now('UTC')` для корректной работы с UTC

### 5. Обработчик исключений RFC 7807
- **Файл**: `bootstrap/app.php`
- **Функционал**: Возвращает ошибки валидации в формате RFC 7807 для API запросов

### 6. Тесты
- **Unit тесты**:
  - `tests/Unit/PublishingServiceTest.php` (13 тестов, включая edge cases)
  - `tests/Unit/PublishedDateNotInFutureRuleTest.php` (7 тестов)
- **Feature тесты**:
  - `tests/Feature/PublishingInvariantsTest.php` (11 тестов)
- **Результат**: Все 31 тест проходят ✅
- **Стабильность**: Все тесты используют `Carbon::setTestNow()` для исключения флаков

### 7. Миграция для индекса
- **Файл**: `database/migrations/2025_11_06_000025_add_publishing_index_to_entries_table.php`
- **Функционал**: Составной индекс `(status, published_at)` для оптимизации запросов `scopePublished()`

## Правила

✅ Если `status = 'published'`, тогда `published_at` обязательно должно быть `<= now()` (UTC)  
✅ Если `status = 'draft'`, значение `published_at` игнорируется  
✅ Автозаполнение даты при публикации: система проставляет `now()` (UTC) только при первичной публикации или переходе `draft → published`  
✅ Идемпотентность: при обновлении уже опубликованной записи без `published_at` историческая дата сохраняется  
✅ Запрет будущих дат: если `status='published'` и `published_at > now()` — ошибка `422`  
✅ Снятие публикации: перевод `published → draft` разрешён без изменений `published_at`  
✅ Обновление опубликованной записи: если дата не меняется — допускается; если меняется на будущее — `422`

## Приёмка

- [x] Валидация реализована в FormRequest + сервисе
- [x] Контроллеры могут использовать `PublishingService::applyAndValidate()`
- [x] Скоуп `published()` добавлен и обновлён для использования UTC
- [x] Все тесты зелёные; ошибки формируются по RFC 7807

## Документация

Полная документация: `docs/implemented/publishing_invariants.md`

## Пример использования

```php
use App\Http\Requests\StoreEntryRequest;
use App\Support\Publishing\PublishingService;
use App\Models\Entry;

public function store(StoreEntryRequest $request, PublishingService $publishingService)
{
    $payload = $request->validated();
    $processed = $publishingService->applyAndValidate($payload);
    $entry = Entry::create($processed);
    
    return response()->json($entry, 201);
}
```

## Формат ошибок RFC 7807

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

