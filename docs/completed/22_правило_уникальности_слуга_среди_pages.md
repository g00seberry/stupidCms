# Задача 22 — Правило уникальности слуга среди Pages

**Цель:** обеспечить детерминированную уникальность слуга (URL-идентификатора) для материалов типа `page` и возвращать **422 Unprocessable Entity** при попытке сохранения дубля с понятным сообщением об ошибке.

---

## 1) Область применения и зависимости

- Применяется ко всем CRUD-операциям над `Entry` с `post_type = 'page'` — создание, обновление, массовый импорт.
- Предполагается, что слуг генерируется сервисом из задачи #21 (slugify), но правило должно корректно работать и при вручную введённом слуге.
- Конфликты с **зарезервированными маршрутами** обрабатываются соседней задачей #23; данное правило отвечает **только за уникальность среди Pages**.

---

## 2) Критерии приёмки

1. При создании Page с `slug`, уже существующим у другой Page, API отдаёт **422** и тело ответа с полем ошибки у `slug`, понятным сообщением (см. §8).  
2. При обновлении Page уникальность проверяется с **игнорированием текущей записи** (можно оставить свой слуг).  
3. Сравнение **без учёта регистра** (де факто слуги храним нижним регистром).  
4. Конфликт проверяется только в рамках `post_type = 'page'` (другие типы записей не учитываются в этой задаче).  
5. Проверка устойчива к гонкам: попытки «продавить» дубль параллельными запросами не проходят (см. §5).  
6. Поведение одинаково для статусов (draft/published) — slug уникален вне зависимости от статуса.

---

## 3) Предпосылки/договорённости

- **Нормализация:** перед валидацией входной `slug` приводится к нижнему регистру, триммится, удаляются повторные дефисы.  
- **Исторические слуги** (если используются редиректы) резервируют значение — повторное использование запрещено (опционально, см. §5.3).  
- **Soft deletes:** по умолчанию **не разрешают** повторного использования — удалённые записи продолжают «держать» slug (проще и безопаснее). Это поведение можно сделать конфигурируемым (`allow_reuse_after_soft_delete=false`).

---

## 4) Публичный контракт (Laravel Validation Rule)

### 4.1 Интерфейс

```php
namespace App\Domain\Pages\Validation;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class UniquePageSlug implements Rule, DataAwareRule
{
    protected array $data = [];

    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function passes($attribute, $value): bool;
    public function message(): string;
}
```

**Особенности:**
- Реализует `DataAwareRule` для доступа к `id`/`entry_id` в момент валидации (нужно, чтобы игнорировать текущую запись).
- Работает поверх репозитория `PageRepository` (или `EntryRepository`) — никаких прямых SQL в правиле.

### 4.2 Использование в FormRequest

```php
public function rules(): array
{
    return [
        'slug' => [
            'required', 'string', 'max:120',
            app(UniquePageSlug::class),
            // отдельно: app(NotReservedRoute::class) — задача #23
        ],
    ];
}
```

### 4.3 Пример внутри контроллера без FormRequest

```php
$request->validate([
    'slug' => ['required','string','max:120', app(UniquePageSlug::class)],
]);
```

---

## 5) Инварианты БД и защита от гонок

### 5.1 Обязательный уникальный индекс

- **Стратегия по умолчанию (рекомендуется):** глобально уникальный индекс `UNIQUE(slug, post_type)` для таблицы `entries`.  
  Это блокирует дубли для Pages и других типов (внутри типа). Слуги храним **нижним регистром**.

**Миграция (MySQL 8+):**
```php
Schema::table('entries', function (Blueprint $t) {
    $t->string('slug', 150)->collation('utf8mb4_0900_ai_ci')->change(); // case-insensitive
    $t->index('post_type');
    $t->unique(['slug', 'post_type'], 'u_entries_slug_posttype');
});
```

> Примечание: частичные индексы для `deleted_at IS NULL` в MySQL недоступны. Если нужна возможность **переиспользовать** slug после soft-delete, введите флаг `is_active` и держите уникальный индекс на `slug, post_type, is_active` с гарантиями со стороны домена, либо храните удалённые слуги в отдельной исторической таблице (рекомендуется для редиректов) и дополнительно проверяйте её.

### 5.2 Транзакции

- Все сохранения проходят в транзакции уровня репозитория/сервиса. На конфликт уникального индекса ловим `PDOException`/`QueryException` и маппим в 422 c ошибкой `slug`.

### 5.3 История слугов (опционально)

- Если ведём таблицу `entry_slugs_history(entry_id, slug)` — добавьте **уникальный индекс** на `slug`, чтобы исключить повторное использование исторических значений. Правило тогда проверяет и историю.

---

## 6) Алгоритм проверки в правиле

1. Нормализовать входной `slug`: `mb_strtolower`, trim, схлопнуть дефисы.  
2. Если пусто после нормализации → провалить отдельным правилом `required`/`min:1`.  
3. Определить ID текущей записи (из `$this->data['id'] ?? null`).  
4. Спросить репозиторий: есть ли **другая** запись с `post_type='page'` и тем же `slug`?  
   - Если да → `passes=false`.  
5. (Опционально) Проверить конфликт по истории слугов.  
6. Вернуть `true`.

Репозиторий должен учитывать soft-deletes в соответствии с политикой §3 (по умолчанию — **не** игнорировать удалённые).

---

## 7) Пример реализации

```php
final class UniquePageSlug implements Rule, DataAwareRule
{
    public function __construct(private readonly EntryRepository $entries) {}

    private array $data = [];
    public function setData($data) { $this->data = $data; return $this; }

    public function passes($attribute, $value): bool
    {
        $slug = strtolower(preg_replace('~-{2,}~', '-', trim((string)$value, ' -_')));
        if ($slug === '') return false;

        $ignoreId = $this->data['id'] ?? $this->data['entry_id'] ?? null;

        return !$this->entries->exists(
            postType: 'page',
            slug: $slug,
            ignoreId: $ignoreId,
            includeSoftDeleted: true // политика по умолчанию
        );
    }

    public function message(): string
    {
        return __('Этот URL уже используется другой страницей. Измените слуг или сохраните с автоправкой.');
    }
}
```

**EntryRepository::exists(...)** — единая точка доступа, внутри Eloquent-запрос:

```php
public function exists(string $postType, string $slug, ?int $ignoreId = null, bool $includeSoftDeleted = true): bool
{
    $q = Entry::query()
        ->where('post_type', $postType)
        ->where('slug', strtolower($slug));

    if ($ignoreId) $q->where('id', '!=', $ignoreId);
    if (!$includeSoftDeleted) $q->whereNull('deleted_at');

    return $q->exists();
}
```

---

## 8) Формат ошибки и локализация

**HTTP 422**
```json
{
  "message": "Данные не прошли валидацию.",
  "errors": {
    "slug": ["Этот URL уже используется другой страницей"]
  }
}
```

- Ключ сообщения хранить в `lang/ru/validation.php`.  
- Для админки предоставить человеко-понятное пояснение и подсказку (кнопка «Автопочинка» — см. §11).

---

## 9) Тесты (PHPUnit / Pest)

### 9.1 Unit — правило

- `passes()` возвращает **false**, если существует другая Page с тем же slug.  
- `passes()` возвращает **true**, если slug уникален в рамках Pages.  
- `passes()` возвращает **true** при апдейте самой себя (ignoreId).

### 9.2 Feature — контроллер

- POST `/api/v1/admin/pages` с дублем `slug` → **422** и ожидаемое сообщение.  
- PATCH `/api/v1/admin/pages/{id}` без изменения `slug` → **200**.  
- Параллельные запросы, пытающиеся создать один и тот же `slug` → один успешен, второй получает **422** (проверяется через искусственную задержку + уникальный индекс).

---

## 10) API-контракты

### POST /api/v1/admin/pages
**Request**
```json
{
  "title": "О компании",
  "slug": "o-kompanii"
}
```
**Responses**
- 201 Created — при успехе.  
- 422 — при конфликте слуга.

### PATCH /api/v1/admin/pages/{id}
- Валидация аналогична. Поле `slug` опционально, но если передано — проверяется правилом.

---

## 11) Интеграция с админкой

- Поле «Slug» снабжено валидацией на фронте (предварительный чек) + показом ошибок из 422.  
- Кнопка «Автоправка»: при нажатии вызывает `/api/v1/admin/utils/slugify?title=...` и подставляет **уникальный** вариант (`stranica-2`, `stranica-3`, …).  
- Сервер остаётся единственным источником истины.

---

## 12) Крайние случаи

- Пустой/пробельный slug → отсекается обычными правилами `required|min:1` до проверки уникальности.  
- Слуги, отличающиеся только регистром → считаются дублями.  
- Слуги с завершающими `-2`, `-3` и т.п. — это **валидные значения**; правило оценивает их как обычные строки.  
- Импорт: при массовой загрузке не полагаться на предварительный чек — только уникальный индекс гарантирует отсутствие гонок.

---

## 13) Чек-лист внедрения

**Цель:** пошагово и без лакун ввести правило уникальности для `page`.

### 13.1 Предварительные условия
- [ ] PHP ≥ 8.2, Laravel ≥ 10.x.
- [ ] Таблица `entries` с полями: `id`, `post_type` (`string`), `slug` (`string`), `deleted_at` (`nullable timestamp`).
- [ ] Слуги сохраняются **в нижнем регистре** (проверить Observer/сервис slugify из задачи #21).

### 13.2 Миграции и индексы
- [ ] Сгенерировать миграцию:
  ```bash
  php artisan make:migration add_unique_index_on_slug_posttype_to_entries
  ```
- [ ] Наполнить миграцию (MySQL 8+):
  ```php
  return new class extends Migration {
      public function up(): void {
          Schema::table('entries', function (Blueprint $t) {
              // унифицируем коллацию для case-insensitive сравнения
              $t->string('slug', 150)->collation('utf8mb4_0900_ai_ci')->change();
              $t->index('post_type');
              $t->unique(['slug','post_type'], 'u_entries_slug_posttype');
          });
      }
      public function down(): void {
          Schema::table('entries', function (Blueprint $t) {
              $t->dropUnique('u_entries_slug_posttype');
              $t->dropIndex(['post_type']);
          });
      }
  };
  ```
- [ ] Для PostgreSQL:
  ```sql
  CREATE UNIQUE INDEX IF NOT EXISTS u_entries_slug_posttype
    ON entries (lower(slug), post_type);
  ```
  (Обернуть в миграцию через `DB::statement`.)
- [ ] Прогнать миграции:
  ```bash
  php artisan migrate
  ```

### 13.3 Репозиторий
- [ ] Убедиться, что есть метод:
  ```php
  public function exists(string $postType, string $slug, ?int $ignoreId = null, bool $includeSoftDeleted = true): bool
  ```
  который учитывает `ignoreId` и soft-deletes по политике проекта (по умолчанию — включены в поиск).

### 13.4 Кастомное правило
- [ ] Создать класс `app/Domain/Pages/Validation/UniquePageSlug.php` (из §7). 
- [ ] Сообщение локализовать через `lang/ru/validation.php` (см. §13.5). 
- [ ] При необходимости зарегистрировать через DI, но достаточно `app(UniquePageSlug::class)`.

### 13.5 Локализация
- [ ] Добавить ключ:
  ```php
  // lang/ru/validation.php
  return [
      'unique_page_slug' => 'Этот URL уже используется другой страницей.',
  ];
  ```
- [ ] В `message()` правила вернуть `__('validation.unique_page_slug')`.

### 13.6 Подключение к валидации
- [ ] В `PageUpsertRequest`/контроллере добавить:
  ```php
  'slug' => ['required','string','max:120', app(UniquePageSlug::class)],
  ```
- [ ] Дополнительно подключить правило задачи #23 `NotReservedRoute` (после данного правила).

### 13.7 Обработка 422 в API
- [ ] Убедиться, что глобальный обработчик ошибок возвращает JSON формата:
  ```json
  {"message":"Данные не прошли валидацию.","errors":{"slug":["Этот URL уже используется другой страницей."]}}
  ```
- [ ] В админке показать текст из `errors.slug[0]` и подсветить поле `Slug`.

### 13.8 Тесты
- [ ] Unit: `UniquePageSlug::passes()` — true/false для базовых сценариев + игнор текущей записи. 
- [ ] Feature: 
  - POST `/api/v1/admin/pages` с дублем slug → **422**. 
  - PATCH собственной страницы без изменения slug → **200**. 
  - Параллельный конфликт (два запроса на один slug) → один **201**, второй **422** (за счёт уникального индекса).
- [ ] Запуск:
  ```bash
  php artisan test --testsuite=Unit
  php artisan test --testsuite=Feature
  ```

### 13.9 Смоук-тесты (ручные)
- [ ] Создать страницу:
  ```bash
  curl -X POST http://localhost:8000/api/v1/admin/pages \
    -H 'Content-Type: application/json' \
    -d '{"title":"О компании","slug":"o-kompanii"}'
  ```
- [ ] Повторить запрос — ожидать **422** с ошибкой по `slug`.

### 13.10 Наблюдаемость и откат
- [ ] Логировать `QueryException` с кодом уникального индекса в канал предупреждений.
- [ ] Для отката: `php artisan migrate:rollback` (или откатить только индекс), затем удалить правило из валидации.

### 13.11 Документация
- [ ] Обновить `README.md` домена Pages: как работает уникальность, политика soft-delete/история слугов, поведение админки (кнопка «Автоправка»).

