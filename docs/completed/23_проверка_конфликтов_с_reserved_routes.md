# 23. Проверка конфликтов с `reserved_routes`

## Цель
Запретить создание/изменение слугов и путей, которые конфликтуют с зарезервированными маршрутами ядра и плагинов. Проверка **регистр-независимая**.

## Контекст
- Плоские URL для `Page`: `/{slug}`.
- Зарезервированные пути/префиксы хранятся в двух местах:
  1) **Конфиг** `config/cms.php` → секция `reserved_routes` (базовые значения ядра).
  2) **БД** → таблица `reserved_routes (path, kind=path|prefix, source=core|plugin)`; дополнительно в плагинах есть `plugin_reserved` (если используется), синхронизируется в `reserved_routes`.
- Вид зарезервированности:
  - `kind=path` — **строгое совпадение** пути (например, `/admin`).
  - `kind=prefix` — **префикс** для любых под-путей (например, `/api/*`).

## Результат
- Валидационное **правило** для Laravel: `NotReservedRoute`.
- Сервис-реестр: `ReservedRouteRegistry` (агрегация из конфига и БД + кэширование).
- Интеграция правила в FormRequest для Entries (поле `slug`).
- Набор модульных/интеграционных тестов.

---

## API и контракты

### Конфиг
```php
// config/cms.php
return [
    'reserved_routes' => [
        'paths' => [
            'admin',         // эквивалентно kind=path для "/admin"
        ],
        'prefixes' => [
            'admin', 'api',  // эквивалентно kind=prefix для "/admin/*", "/api/*"
        ],
    ],
];
```
> Примечание: значения указываются **без ведущего слэша**, будут нормализованы.

### Сервис `ReservedRouteRegistry`
```php
final class ReservedRouteRegistry
{
    public function __construct(private CacheRepository $cache) {}

    /** @return array{paths: string[], prefixes: string[]} */
    public function all(): array { /* merge(config+DB), normalize+dedupe, cache */ }

    public function isReservedPath(string $path): bool { /* exact match (case-insensitive) */ }
    public function isReservedPrefix(string $path): bool { /* startsWith by segment (case-insensitive) */ }

    /** Проверка по **slug** (первый сегмент), без ведущего слэша */
    public function isReservedSlug(string $slug): bool { /* path==slug || prefix==slug */ }
}
```
Кэш: тег `reserved_routes`, TTL = 60 секунд. Инвалидация — при изменении таблицы/команды sync плагинов.

### Валидатор `NotReservedRoute`
```php
use Illuminate\Contracts\Validation\Rule;

class NotReservedRoute implements Rule
{
    public function __construct(private ReservedRouteRegistry $registry) {}

    public function passes($attribute, $value): bool
    {
        $slug = self::normalizeSlug((string)$value);
        return !$this->registry->isReservedSlug($slug);
    }

    public function message(): string
    {
        return __('validation.slug_reserved');
    }

    private static function normalizeSlug(string $slug): string
    {
        $slug = trim($slug, " \t\n\r\0\x0B/\\");
        if (class_exists(\Normalizer::class)) {
            $slug = \Normalizer::normalize($slug, \Normalizer::FORM_C);
        }
        return mb_strtolower($slug, 'UTF-8');
    }
}
```

### Интеграция в FormRequest
```php
class StoreEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required','string','max:255'],
            'slug'  => [
                'required','string','max:255',
                new UniquePageSlug(),       // задача 22
                new NotReservedRoute(),     // текущая задача
            ],
        ];
    }
}
```

---

## Нормализация и сравнение
- **К регистру нечувствительно**: сравнение на `mb_strtolower()` (или collation `utf8mb4_unicode_ci`), плюс NFC-нормализация.
- Внутренний формат без ведущего слэша, только **первый сегмент** (для slug).
- Для `kind=prefix` совпадение проверяется по границе сегмента: `slug === prefix` (так как slug — один сегмент). Для проверок полноценных путей — `startsWith(prefix . '/')`.

## Алгоритм проверки (для `slug`)
1. Нормализовать значение: `trim('/'), lower, NFC`.
2. Получить из реестра объединённые списки `paths` и `prefixes`.
3. Конфликт, если `slug ∈ paths` **ИЛИ** `slug ∈ prefixes`.
4. В случае конфликта вернуть 422 с сообщением `validation.slug_reserved`.

## Схема/данные
**`reserved_routes`**:
- `path VARCHAR(255)` — **уникальный**, хранится без `/`; колляция `utf8mb4_unicode_ci`.
- `kind ENUM('path','prefix')` — тип зарезервированности.
- `source ENUM('core','plugin')` — источник.
- Индексы: `UNIQUE(path, kind)` (или `UNIQUE(path)` при инварианте одного `kind` на путь).

Seed по умолчанию:
- `('admin','path','core')`
- `('admin','prefix','core')`
- `('api','prefix','core')`

> При установке/включении плагина его `plugin_reserved` синхронизируется в `reserved_routes`.

## Сообщения об ошибках (i18n)
```php
// lang/ru/validation.php
'slug_reserved' => 'Значение поля :attribute конфликтует с зарезервированными маршрутами (например: admin, api).',
```

## Тесты
### Unit: `ReservedRouteRegistryTest`
- Собирает конфиг+фикстуры БД, проверяет нормализацию и дедуп.
- Кейсы:
  - `admin` → зарезервирован (path/prefix — неважно).
  - `Admin`, `ADMIN` → зарезервирован (case-insensitive).
  - `api` → зарезервирован.
  - `api-v1` → **не** зарезервирован (slug — один сегмент, префикс не совпадает полностью).
  - `about` → не зарезервирован.

### Feature: `EntryCreateReservedSlugTest`
- POST `/api/v1/admin/entries` c `slug=admin` → `422` + `slug_reserved`.
- POST c `slug=api` → `422`.
- POST c `slug=about` → `201`.
- Обновление существующей записи на `slug=admin` → `422`.

## Производительность и кэш
- Объединённые списки держать в памяти запроса; общий кэш — 60с.
- Проверка O(1): преобразовать списки в `array_flip()`/HashSet для membership.
- Инвалидация кэша через события/команду:`cms:plugins:sync` и CRUD `reserved_routes`.

## Граничные случаи
- Ввод со слэшами/пробелами по краям → тримминг.
- Юникод (NFC) и смешанный регистр.
- Процент-кодирование/ESC-последовательности **не рассматриваются** для `slug` (входное значение — уже нормализованный slug).
- Совпадение с системной домашней страницей `/` — не относится к задаче (обрабатывается отдельно).

## Безопасность
- Валидация на уровне **HTTP API** и **доменного сервиса** (двойной барьер) во избежание обходов.
- Логировать попытки конфликтных значений (уровень `info`/`notice` в `audits`).

## CLI (опционально)
- `php artisan cms:reserved:cache:clear` — сброс кэша реестра.
- `php artisan cms:reserved:list` — сверка текущего набора (config+DB).

## Критерии приёмки
- `POST`/`PUT` с `slug=admin` → **422** с ошибкой `slug_reserved`.
- `POST`/`PUT` с `slug=api` → **422**.
- Кейс `Admin`/`API` также отклоняется (регистр не важен).
- Нормальные слаги проходят без ошибок.

## Чек-лист выполнения
- [ ] Конфиг `reserved_routes` добавлен и задокументирован.
- [ ] Таблица `reserved_routes` содержит базовые записи (`admin`, `api`).
- [ ] Сервис `ReservedRouteRegistry` реализован + кэширование.
- [ ] Правило `NotReservedRoute` подключено в FormRequest для Entries.
- [ ] Unit/Feature тесты зелёные.
- [ ] Команды/инвалидация кэша задокументированы.
