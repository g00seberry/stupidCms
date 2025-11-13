# План PHPDoc документирования проекта

**Дата составления:** 2025-01-13  
**Проект:** stupidCms (Laravel 12, PHP 8.3+)  
**Цель:** Полное покрытие кодовой базы PHPDoc комментариями для улучшения читаемости, поддержки и интеграции с IDE.

---

## Текущее состояние

### ✅ Хорошо документировано
- `app/Domain/Auth/JwtService.php` — полная документация методов, параметров, исключений
- Контроллеры — аннотации Scribe для API (`@group`, `@response`), но отсутствуют PHPDoc для параметров методов

### ⚠️ Частично документировано
- `app/Domain/Entries/DefaultEntrySlugService.php` — есть описания методов, но неполные типы параметров
- `app/Models/Entry.php` — только `getStatuses()` документирован
- `app/Http/Resources/Admin/EntryResource.php` — базовая документация `toArray()`
- `app/Policies/EntryPolicy.php` — стандартные комментарии Laravel, без деталей

### ❌ Не документировано
- Casts (`app/Casts/`)
- Большинство моделей (связи, скоупы, методы)
- Domain Services (кроме JwtService)
- HTTP Requests (валидация)
- HTTP Resources (кроме базовых)
- Rules (кастомные правила валидации)
- Observers (события моделей)
- Middleware
- Providers
- Commands
- Events
- Exceptions
- Value Objects
- DTO

---

## Приоритеты документирования

### Приоритет 1: Критичные компоненты (публичные API)
1. **Models** — Eloquent модели (18 сущностей)
2. **Domain Services** — бизнес-логика (39 сущностей)
3. **HTTP Controllers** — API endpoints (14+ контроллеров)
4. **HTTP Resources** — форматирование ответов (30 ресурсов)
5. **HTTP Requests** — валидация входных данных (31 запрос)

### Приоритет 2: Вспомогательные компоненты
6. **Policies** — авторизация (6 политик)
7. **Rules** — кастомные правила валидации (6 правил)
8. **Observers** — события моделей (1 observer)
9. **Middleware** — промежуточное ПО (7 middleware)
10. **Providers** — сервис-провайдеры (9 провайдеров)

### Приоритет 3: Инфраструктура
11. **Casts** — кастомные касты (1 cast)
12. **Commands** — консольные команды (10 команд)
13. **Events** — события приложения (2 события)
14. **Exceptions** — исключения (13+ исключений)
15. **Value Objects** — объекты-значения
16. **DTO** — объекты передачи данных

---

## Стандарты PHPDoc

### Обязательные элементы

#### Для классов
```php
/**
 * Краткое описание класса (одна строка).
 *
 * Подробное описание, если необходимо (несколько строк).
 * Может содержать примеры использования, важные замечания.
 *
 * @package App\Domain\Entries
 */
```

#### Для методов
```php
/**
 * Краткое описание метода.
 *
 * Подробное описание, если необходимо.
 *
 * @param string $param Описание параметра
 * @param int|null $optional Опциональный параметр
 * @return array<string, mixed> Описание возвращаемого значения
 * @throws \InvalidArgumentException Когда выбрасывается исключение
 */
```

#### Для свойств
```php
/**
 * Описание свойства.
 *
 * @var string
 */
private string $field;
```

### Типы данных

- **Простые:** `string`, `int`, `bool`, `float`, `array`
- **Nullable:** `string|null`, `?int`
- **Union:** `int|string`
- **Массивы:** `array<string>`, `array<string, mixed>`, `array<int, Entry>`
- **Коллекции:** `\Illuminate\Support\Collection<int, Entry>`
- **Eloquent:** `\Illuminate\Database\Eloquent\Builder<Entry>`
- **Классы проекта:** `Entry`, `EntryResource`, `EntrySlugService`

### Специальные теги

- `@throws` — для всех исключений
- `@deprecated` — для устаревших методов
- `@internal` — для внутренних методов
- `@see` — ссылки на связанные классы/методы
- `@since` — версия добавления (опционально)

---

## Детальный план по категориям

### 1. Models (18 сущностей)

**Файлы:**
- `app/Models/Entry.php`
- `app/Models/EntryMedia.php`
- `app/Models/EntrySlug.php`
- `app/Models/Media.php`
- `app/Models/MediaVariant.php`
- `app/Models/Option.php`
- `app/Models/Outbox.php`
- `app/Models/Plugin.php`
- `app/Models/PostType.php`
- `app/Models/Redirect.php`
- `app/Models/RefreshToken.php`
- `app/Models/ReservedRoute.php`
- `app/Models/RouteReservation.php`
- `app/Models/Taxonomy.php`
- `app/Models/Term.php`
- `app/Models/TermTree.php`
- `app/Models/User.php`
- `app/Models/Audit.php`

**Что документировать:**
- Класс: назначение модели, таблица БД, основные поля
- Константы: значения статусов, типов и т.д.
- Свойства `$fillable`/`$guarded`: описание защищённых полей
- Свойства `$casts`: описание кастов
- Методы связей (`belongsTo`, `hasMany`, `belongsToMany`):
  - Тип возвращаемого значения
  - Описание связи
  - Условия связи (если есть)
- Скоупы (`scope*`):
  - Параметры
  - Что фильтрует
  - Возвращаемое значение
- Публичные методы:
  - Параметры
  - Возвращаемое значение
  - Исключения

**Пример:**
```php
/**
 * Eloquent модель для записей контента (Entry).
 *
 * Представляет единицу контента в CMS: статьи, страницы, посты и т.д.
 * Поддерживает мягкое удаление, публикацию по расписанию, связи с термами и медиа.
 *
 * @property int $id
 * @property int $post_type_id
 * @property string $title
 * @property string $slug
 * @property string $status Статус записи: 'draft' или 'published'
 * @property array $data_json Произвольные структурированные данные контента
 * @property array|null $seo_json SEO-метаданные
 * @property \Illuminate\Support\Carbon|null $published_at Дата публикации
 * @property string|null $template_override Кастомный шаблон Blade
 * @property int $author_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\PostType $postType
 * @property-read \App\Models\User $author
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EntrySlug> $slugs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Term> $terms
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Media> $media
 */
class Entry extends Model
{
    /**
     * Статус: черновик.
     */
    public const STATUS_DRAFT = 'draft';

    /**
     * Статус: опубликовано.
     */
    public const STATUS_PUBLISHED = 'published';

    /**
     * Получить список возможных статусов записи.
     *
     * @return array<string> Массив статусов: ['draft', 'published']
     */
    public static function getStatuses(): array

    /**
     * Связь с типом записи (PostType).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\PostType, \App\Models\Entry>
     */
    public function postType()

    /**
     * Связь с автором записи.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\Entry>
     */
    public function author()

    /**
     * Связь с историей slug'ов записи.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\EntrySlug, \App\Models\Entry>
     */
    public function slugs()

    /**
     * Связь с термами (категории, теги и т.д.).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Term, \App\Models\Entry>
     */
    public function terms()

    /**
     * Связь с медиа-файлами, привязанными к записи.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Media, \App\Models\Entry>
     */
    public function media()

    /**
     * Скоуп: только опубликованные записи.
     *
     * Фильтрует записи со статусом 'published', у которых published_at не null
     * и не превышает текущее время (UTC).
     *
     * @param \Illuminate\Database\Eloquent\Builder<Entry> $q
     * @return \Illuminate\Database\Eloquent\Builder<Entry>
     */
    public function scopePublished(Builder $q): Builder

    /**
     * Скоуп: записи определённого типа.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Entry> $q
     * @param string $postTypeSlug Slug типа записи
     * @return \Illuminate\Database\Eloquent\Builder<Entry>
     */
    public function scopeOfType(Builder $q, string $postTypeSlug): Builder

    /**
     * Получить публичный URL записи.
     *
     * Для типа 'page' возвращает плоский URL (/slug),
     * для остальных — иерархический (/type/slug).
     *
     * @return string Публичный URL записи
     */
    public function url(): string
}
```

---

### 2. Domain Services (39 сущностей)

**Категории:**
- Auth (`app/Domain/Auth/`)
- Entries (`app/Domain/Entries/`)
- Media (`app/Domain/Media/`)
- Options (`app/Domain/Options/`)
- Pages (`app/Domain/Pages/`)
- Plugins (`app/Domain/Plugins/`)
- Routing (`app/Domain/Routing/`)
- Sanitizer (`app/Domain/Sanitizer/`)
- Search (`app/Domain/Search/`)
- View (`app/Domain/View/`)

**Что документировать:**
- Класс: назначение сервиса, ответственность
- Конструктор: зависимости и их назначение
- Публичные методы:
  - Полное описание параметров
  - Возвращаемые значения
  - Исключения
  - Побочные эффекты (если есть)
- Приватные методы: описание логики

**Пример:**
```php
/**
 * Сервис для управления историей slug'ов записей.
 *
 * Отслеживает изменения slug'ов Entry и сохраняет историю в таблице entry_slugs.
 * Гарантирует атомарность операций и корректность флага is_current.
 *
 * @package App\Domain\Entries
 */
final class DefaultEntrySlugService implements EntrySlugService
{
    /**
     * Создать текущую запись истории после создания Entry.
     *
     * Если slug пуст, операция не выполняется.
     * Использует транзакцию для атомарности.
     *
     * @param \App\Models\Entry $entry Созданная запись
     * @return void
     */
    public function onCreated(Entry $entry): void

    /**
     * Синхронизировать историю при изменении slug.
     *
     * Создаёт новую запись в истории, если slug изменился.
     * Атомарно обновляет флаг is_current для всех записей истории.
     *
     * @param \App\Models\Entry $entry Обновлённая запись
     * @param string $oldSlug Предыдущий slug
     * @param bool $dispatchEvent Диспатчить событие EntrySlugChanged (по умолчанию true)
     * @return bool true, если slug изменился; false, если остался прежним
     */
    public function onUpdated(Entry $entry, string $oldSlug, bool $dispatchEvent = true): bool

    /**
     * Получить текущий slug для Entry.
     *
     * @param int $entryId ID записи
     * @return string|null Текущий slug или null, если не найден
     */
    public function currentSlug(int $entryId): ?string
}
```

---

### 3. HTTP Controllers (14+ контроллеров)

**Файлы:**
- `app/Http/Controllers/Controller.php` (базовый)
- `app/Http/Controllers/Admin/V1/*.php` (14 контроллеров)
- `app/Http/Controllers/Auth/*.php` (4 контроллера)
- `app/Http/Controllers/AdminPingController.php`
- `app/Http/Controllers/FallbackController.php`
- `app/Http/Controllers/HomeController.php`
- `app/Http/Controllers/PageController.php`
- `app/Http/Controllers/SearchController.php`

**Что документировать:**
- Класс: назначение контроллера, группа роутов
- Конструктор: зависимости
- Методы действий:
  - **Важно:** Scribe аннотации уже есть, НЕ дублировать
  - Добавить PHPDoc для параметров методов (не аннотаций)
  - Типы возвращаемых значений
  - Исключения (кроме стандартных HTTP)
  - Приватные методы: полная документация

**Пример:**
```php
/**
 * Контроллер для управления записями (Entry) в админ-панели.
 *
 * Обрабатывает CRUD операции для записей контента через REST API.
 * Требует аутентификации и прав администратора.
 *
 * @group Admin ▸ Entries
 */
class EntryController extends Controller
{
    /**
     * Список записей с фильтрами и пагинацией.
     *
     * Поддерживает фильтрацию по типу, статусу, автору, термам, датам.
     * Поиск по названию и slug. Сортировка и пагинация.
     *
     * @param \App\Http\Requests\Admin\IndexEntriesRequest $request Валидированный запрос с фильтрами
     * @return \App\Http\Resources\Admin\EntryCollection Коллекция записей с метаданными пагинации
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на просмотр записей
     */
    public function index(IndexEntriesRequest $request): EntryCollection

    /**
     * Получение записи по ID (включая удалённые).
     *
     * @param int $id ID записи
     * @return \App\Http\Resources\Admin\EntryResource Ресурс записи
     * @throws \App\Support\Errors\ThrowsErrors Если запись не найдена
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на просмотр
     */
    public function show(int $id): EntryResource

    /**
     * Generate a unique slug for the entry.
     *
     * @param string $title Заголовок для генерации slug
     * @param string $postTypeSlug Slug типа записи для проверки уникальности
     * @return string Уникальный slug
     */
    private function generateUniqueSlug(string $title, string $postTypeSlug): string
}
```

---

### 4. HTTP Resources (30 ресурсов)

**Что документировать:**
- Класс: назначение ресурса, формат ответа
- Метод `toArray()`:
  - Структура возвращаемого массива
  - Условия включения полей (`when()`)
  - Формат дат, JSON полей
- Приватные методы: трансформации данных

**Пример:**
```php
/**
 * API Resource для Entry в админ-панели.
 *
 * Форматирует Entry для ответа API, включая связанные сущности
 * (postType, author, terms) при их загрузке.
 *
 * @package App\Http\Resources\Admin
 */
class EntryResource extends AdminJsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Возвращает массив с полями записи, включая:
     * - Базовые поля (id, title, slug, status)
     * - JSON поля (content_json, meta_json) как объекты
     * - Связанные сущности (author, terms) при наличии
     * - Даты в ISO 8601 формате
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed> Массив данных записи
     */
    public function toArray($request): array

    /**
     * Рекурсивно трансформирует JSON данные, заменяя пустые массивы на объекты.
     *
     * Обеспечивает консистентный формат JSON в ответах API.
     *
     * @param mixed $value Значение для трансформации
     * @return mixed Трансформированное значение
     */
    private function transformJson(mixed $value): mixed
}
```

---

### 5. HTTP Requests (31 запрос)

**Что документировать:**
- Класс: назначение запроса, валидируемые данные
- Метод `authorize()`: условия авторизации
- Метод `rules()`: описание правил валидации
- Метод `messages()`: кастомные сообщения (если есть)
- Метод `prepareForValidation()`: предобработка данных
- Метод `withValidator()`: кастомная валидация

**Пример:**
```php
/**
 * Request для создания новой записи (Entry).
 *
 * Валидирует данные для создания записи контента:
 * - Обязательные: post_type, title
 * - Опциональные: slug (автогенерация), content_json, meta_json, published_at
 * - Проверяет уникальность slug в рамках типа записи
 * - Проверяет зарезервированные пути
 *
 * @package App\Http\Requests\Admin
 */
class StoreEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Авторизация обрабатывается middleware маршрута.
     *
     * @return bool
     */
    public function authorize(): bool

    /**
     * Get the validation rules that apply to the request.
     *
     * Правила валидации для всех полей запроса, включая кастомные правила:
     * - UniqueEntrySlug: проверка уникальности slug
     * - ReservedSlug: проверка зарезервированных путей
     * - Publishable: проверка возможности публикации
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array

    /**
     * Prepare the data for validation.
     *
     * Автоматически устанавливает published_at в текущее время,
     * если is_published=true и published_at не указан.
     *
     * @return void
     */
    protected function prepareForValidation(): void
}
```

---

### 6. Policies (6 политик)

**Что документировать:**
- Класс: назначение политики, модель
- Методы авторизации:
  - Условия доступа
  - Проверяемые права
  - Логика разрешений

**Пример:**
```php
/**
 * Политика авторизации для Entry.
 *
 * Определяет права доступа к записям контента на основе
 * административных разрешений пользователя.
 *
 * @package App\Policies
 */
class EntryPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user
     * @return bool
     */
    public function viewAny(User $user): bool

    /**
     * Determine whether the user can publish/unpublish the entry.
     *
     * Требует права 'manage.entries'.
     *
     * @param \App\Models\User $user
     * @param \App\Models\Entry $entry
     * @return bool
     */
    public function publish(User $user, Entry $entry): bool
}
```

---

### 7. Rules (6 правил)

**Что документировать:**
- Класс: назначение правила, валидируемое поле
- Конструктор: параметры правила
- Метод `validate()`: логика валидации, условия ошибки

**Пример:**
```php
/**
 * Правило валидации: уникальность slug записи в рамках типа записи.
 *
 * Проверяет, что slug не занят другой записью того же типа.
 * Учитывает мягко удалённые записи. Поддерживает исключение записи по ID.
 *
 * @package App\Rules
 */
class UniqueEntrySlug implements ValidationRule
{
    /**
     * @param string $postTypeSlug Slug типа записи для проверки уникальности
     * @param int|null $exceptEntryId ID записи, которую исключить из проверки (для update)
     */
    public function __construct(
        private string $postTypeSlug,
        private ?int $exceptEntryId = null
    ) {}

    /**
     * Run the validation rule.
     *
     * Проверяет существование PostType и уникальность slug в его рамках.
     * Если slug занят (включая мягко удалённые записи), добавляет ошибку валидации.
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Значение для валидации
     * @param \Closure(string, string): void $fail Callback для добавления ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
}
```

---

### 8. Observers (1 observer)

**Что документировать:**
- Класс: назначение observer, отслеживаемая модель
- Конструктор: зависимости
- Методы событий: когда вызываются, что делают
- Приватные методы: логика обработки

**Пример:**
```php
/**
 * Observer для модели Entry.
 *
 * Обрабатывает события жизненного цикла Entry:
 * - Генерация slug из title (если не указан)
 * - Проверка уникальности slug
 * - Синхронизация истории slug'ов
 * - Санитизация HTML полей в data_json
 *
 * @package App\Observers
 */
class EntryObserver
{
    /**
     * Handle the Entry "creating" event.
     *
     * Генерирует slug из title (если не указан) и санитизирует HTML поля.
     *
     * @param \App\Models\Entry $entry
     * @return void
     */
    public function creating(Entry $entry): void

    /**
     * Handle the Entry "updating" event.
     *
     * Пересчитывает slug при изменении title или slug.
     * Сохраняет старый slug для истории.
     *
     * @param \App\Models\Entry $entry
     * @return void
     */
    public function updating(Entry $entry): void
}
```

---

### 9. Casts (1 cast)

**Что документировать:**
- Класс: назначение каста, тип данных
- Методы `get()` и `set()`: логика преобразования

**Пример:**
```php
/**
 * Eloquent cast для JSON значений.
 *
 * Преобразует JSON строки в массивы при чтении и массивы в JSON строки при записи.
 * Обрабатывает ошибки декодирования (возвращает null) и кодирования (выбрасывает исключение).
 *
 * @package App\Casts
 */
final class AsJsonValue implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * Декодирует JSON строку в массив. При ошибке декодирования возвращает null.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key Имя атрибута
     * @param mixed $value Значение из БД (JSON строка или null)
     * @param array<string, mixed> $attributes Все атрибуты модели
     * @return mixed Декодированное значение или null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed

    /**
     * Transform the attribute to its underlying model values.
     *
     * Кодирует значение в JSON строку. При ошибке кодирования выбрасывает InvalidArgumentException.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key Имя атрибута
     * @param mixed $value Значение для кодирования
     * @param array<string, mixed> $attributes Все атрибуты модели
     * @return string JSON строка
     * @throws \InvalidArgumentException Если значение не может быть закодировано в JSON
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
}
```

---

### 10. Commands (10 команд)

**Что документировать:**
- Класс: назначение команды, описание
- Свойство `$signature`: параметры команды
- Свойство `$description`: описание команды
- Метод `handle()`: логика выполнения

**Пример:**
```php
/**
 * Команда для генерации документации из кодовой базы.
 *
 * Сканирует проект и генерирует Markdown файлы с описанием сущностей:
 * модели, сервисы, контроллеры, роуты и т.д.
 *
 * @package App\Console\Commands
 */
final class GenerateDocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:generate 
                            {--type= : Generate documentation for specific type only}
                            {--force : Overwrite existing files}
                            {--cache : Use cached scan results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate documentation from codebase';

    /**
     * Execute the console command.
     *
     * Запускает сканирование кодовой базы, группирует сущности по типам
     * и генерирует Markdown файлы и индекс.
     *
     * @param \App\Documentation\ScannerManager $scannerManager
     * @return int Код возврата (0 = успех, 1 = ошибка)
     */
    public function handle(ScannerManager $scannerManager): int
}
```

---

### 11. Middleware (7 middleware)

**Что документировать:**
- Класс: назначение middleware, когда применяется
- Метод `handle()`: логика обработки запроса

---

### 12. Providers (9 провайдеров)

**Что документировать:**
- Класс: назначение провайдера
- Методы `register()` и `boot()`: регистрируемые сервисы

---

### 13. Events (2 события)

**Что документировать:**
- Класс: назначение события, когда диспатчится
- Свойства: данные события

---

### 14. Exceptions (13+ исключений)

**Что документировать:**
- Класс: назначение исключения, когда выбрасывается
- Конструктор: параметры исключения

---

### 15. Value Objects и DTO

**Что документировать:**
- Класс: назначение объекта
- Свойства: описание полей
- Методы: логика работы

---

## Порядок выполнения

### Этап 1: Подготовка (1-2 часа)
1. Создать шаблоны PHPDoc для каждой категории
2. Настроить IDE/линтер для проверки PHPDoc
3. Определить стандарты форматирования

### Этап 2: Приоритет 1 (20-30 часов)
1. Models (18 файлов) — ~8-10 часов
2. Domain Services (39 файлов) — ~10-12 часов
3. HTTP Controllers (14+ файлов) — ~4-6 часов
4. HTTP Resources (30 файлов) — ~4-6 часов
5. HTTP Requests (31 файл) — ~4-6 часов

### Этап 3: Приоритет 2 (10-15 часов)
6. Policies (6 файлов) — ~2 часа
7. Rules (6 файлов) — ~2 часа
8. Observers (1 файл) — ~1 час
9. Middleware (7 файлов) — ~2-3 часа
10. Providers (9 файлов) — ~3-4 часа

### Этап 4: Приоритет 3 (8-12 часов)
11. Casts (1 файл) — ~0.5 часа
12. Commands (10 файлов) — ~3-4 часа
13. Events (2 файла) — ~1 час
14. Exceptions (13+ файлов) — ~2-3 часа
15. Value Objects и DTO — ~2-4 часа

**Общее время:** 38-57 часов

---

## Инструменты и проверка

### Линтеры
- `phpstan/phpstan` — статический анализ с проверкой PHPDoc
- `phpstan/phpstan-strict-rules` — строгие правила
- `laravel/pint` — форматирование (уже используется)

### Автоматизация
- CI/CD проверка PHPDoc через PHPStan
- Pre-commit hook для проверки перед коммитом

### Метрики
- Процент покрытия PHPDoc (цель: 100%)
- Количество предупреждений PHPStan (цель: 0)

---

## Чеклист для каждого файла

- [ ] Класс документирован (описание, @package)
- [ ] Конструктор документирован (параметры, зависимости)
- [ ] Все публичные методы документированы
- [ ] Все приватные методы документированы (или помечены @internal)
- [ ] Все свойства документированы (или явно типизированы)
- [ ] Все параметры методов типизированы в PHPDoc
- [ ] Все возвращаемые значения типизированы
- [ ] Все исключения указаны через @throws
- [ ] Проверка через PHPStan без ошибок
- [ ] Соответствие стандартам проекта

---

## Примечания

1. **Scribe аннотации:** Не дублировать документацию API endpoints, которая уже есть в Scribe аннотациях. PHPDoc должен дополнять, а не повторять.

2. **Типизация PHP 8.3+:** Использовать строгую типизацию PHP везде, где возможно. PHPDoc должен дополнять, а не заменять типы.

3. **Eloquent связи:** Всегда указывать полный тип связи с generic параметрами:
   ```php
   @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\PostType, \App\Models\Entry>
   ```

4. **Массивы:** Всегда указывать структуру массива:
   ```php
   @return array<string, mixed>
   @param array<int, Entry> $entries
   ```

5. **Исключения:** Указывать все возможные исключения, включая вложенные вызовы.

---

**Следующие шаги:**
1. Утвердить план с командой
2. Начать с Приоритета 1, начиная с Models
3. Регулярно проверять прогресс через PHPStan
4. Обновлять документацию при изменении кода

