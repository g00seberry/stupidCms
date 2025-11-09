---
owner: "@backend-team"
system_of_record: "narrative"
review_cycle_days: 60
last_reviewed: 2025-11-08
related_code:
    - "app/Models/Option.php"
    - "app/Domain/Options/OptionsRepository.php"
    - "app/Http/Controllers/Admin/OptionsController.php"
    - "app/Http/Requests/Admin/Options/IndexOptionsRequest.php"
    - "app/Http/Requests/Admin/Options/PutOptionRequest.php"
    - "app/Http/Resources/Admin/OptionResource.php"
    - "app/Policies/OptionPolicy.php"
    - "app/Helpers/options.php"
    - "config/options.php"
---

# Options (настройки сайта)

**Options** — namespaced key-value хранилище JSON-значений для глобальных настроек stupidCms.

## Концепция

Настройки, которые требуется менять через админку, а не через `.env` или код:

-   глобальные параметры сайта (title, домашняя запись и т.п.)
-   feature flags
-   системные интеграции (в зашифрованном виде)

Ключевые особенности:

-   адресация по `namespace/key`
-   хранение любого JSON-типа без трансформаций
-   soft delete + restore для audit-friendly операций
-   строгая валидация входных данных и контролируемый API

## Модель данных

`database/migrations/*_create_options_table.php` создаёт таблицу `options`:

| Поле          | Тип                  | Описание                       |
| ------------- | -------------------- | ------------------------------ |
| `id`          | ULID (PK)            | глобальный идентификатор       |
| `namespace`   | string(64)           | `^[a-z0-9_][a-z0-9_.-]{1,63}$` |
| `key`         | string(64)           | `^[a-z0-9_][a-z0-9_.-]{1,63}$` |
| `value_json`  | json (NOT NULL)      | сериализуемое JSON-значение    |
| `description` | string(255) nullable | human-readable комментарий     |
| timestamps    |                      | `created_at`, `updated_at`     |
| soft deletes  |                      | `deleted_at`                   |

Индексы:

-   `UNIQUE(namespace, key)`
-   `INDEX(namespace)`
-   `INDEX(deleted_at)`

```php
class Option extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $casts = [
        'value_json' => \App\Casts\AsJsonValue::class,
    ];
}
```

## Доступ к значениям

### Хелперы

-   `options(string $namespace, string $key, mixed $default = null): mixed`
-   `option_set(string $namespace, string $key, mixed $value, ?string $description = null): void`

```php
$homeEntry = options('site', 'home_entry_id');
option_set('features', 'new_editor', ['enabled' => true], description: 'Включаем новый редактор');
```

### Репозиторий

`App\Domain\Options\OptionsRepository` централизует чтение/запись и кэширование (Cache tags `options`, `options:{namespace}`):

-   `get(ns, key, default)`
-   `set(ns, key, value, description?)`
-   `delete(ns, key)` — soft delete
-   `restore(ns, key)`

## JSON-валидация

`App\Rules\JsonValue` проверяет сериализацию через `json_encode(JSON_THROW_ON_ERROR)` и ограничивает размер (по умолчанию ≤ 64 KB). При превышении или невалидном типе API отвечает `422 INVALID_JSON_VALUE` (RFC7807).

```php
return [
    'value' => ['required', new JsonValue(maxBytes: 65536)],
];
```

## События и кэш

После `OptionsRepository::set()` диспатчится `App\Events\OptionChanged` с `namespace`, `key`, `value`, `oldValue`. Используйте его для инвалидации кэшей или побочных действий.

## Админский API

Контроллер: `app/Http/Controllers/Admin/OptionsController.php`  
Политика: `app/Policies/OptionPolicy.php`

| Method | Path                                              | Ability           | Throttle | Описание                     |
| ------ | ------------------------------------------------- | ----------------- | -------- | ---------------------------- |
| GET    | `/api/v1/admin/options/{namespace}`               | `options.read`    | 120 rpm  | Список опций namespace       |
| GET    | `/api/v1/admin/options/{namespace}/{key}`         | `options.read`    | 120 rpm  | Получить одну опцию          |
| PUT    | `/api/v1/admin/options/{namespace}/{key}`         | `options.write`   | 30 rpm   | Upsert (создание/обновление) |
| DELETE | `/api/v1/admin/options/{namespace}/{key}`         | `options.delete`  | 30 rpm   | Soft delete                  |
| POST   | `/api/v1/admin/options/{namespace}/{key}/restore` | `options.restore` | 30 rpm   | Восстановление               |

-   namespace/key валидируются regex `^[a-z0-9_][a-z0-9_.-]{1,63}$`
-   ответы и ошибки — RFC7807 (`application/problem+json`)
-   `OptionResource` возвращает исходный JSON без мутаций, включая `deleted_at`

### Пример upsert

```bash
curl -X PUT \
  https://cms.local/api/v1/admin/options/site/home_entry_id \
  -H "Content-Type: application/json" \
  -H "Cookie: jwt=..." \
  -d '{"value":"01HXZPQ4GQ9E6BV0V8GWV3CEX9","description":"Домашняя запись"}'
```

Ответ `201 Created` (при создании):

```json
{
    "data": {
        "id": "01HXZPQ4G5B7C0D1E2F3G4H5JK",
        "namespace": "site",
        "key": "home_entry_id",
        "value": "01HXZPQ4GQ9E6BV0V8GWV3CEX9",
        "description": "Домашняя запись",
        "updated_at": "2025-11-08T11:30:00Z",
        "deleted_at": null
    }
}
```

### Ошибки

```json
{
    "type": "https://stupidcms.dev/problems/invalid-option-identifier",
    "title": "Validation error",
    "status": 422,
    "code": "INVALID_OPTION_IDENTIFIER",
    "errors": {
        "namespace": ["The selected namespace is invalid."]
    }
}
```

## Примеры

### Feature Flags

```php
option_set('features', 'new_editor', true);

if (options('features', 'new_editor', false)) {
    return view('admin.editor.new');
}
```

### Интеграции

```php
option_set('integration', 'mailchimp', [
    'api_key' => encrypt('abc123'),
    'list_id' => 'foo',
]);

$cfg = options('integration', 'mailchimp');
$mailchimp = new MailchimpClient(decrypt($cfg['api_key']));
```

### Настройки темы

```php
option_set('theme', 'ui', [
    'primary_color' => '#007bff',
    'font' => 'Inter',
    'logo_url' => '/media/logo.png',
]);

$theme = options('theme', 'ui', []);
```

## Кэширование

OptionsRepository автоматически инвалидацирует кэш при `set/delete/restore`. Для точечного сброса используйте теги:

```php
Cache::tags(['options', 'options:site'])->forget('opt:site:home_entry_id');
```

## Безопасность

-   Не храните секреты в открытом виде — шифруйте значения (`Crypt::encryptString`)
-   Не используйте options для данных конкретных сущностей — для этого `Entry.data_json`
-   Разрешённые ключи фиксируйте в `config/options.php` (allow-list)

```php
option_set('integration', 'stripe', [
    'secret_key' => Crypt::encryptString($key),
]);

$secret = Crypt::decryptString(options('integration', 'stripe')['secret_key']);
```

## Best Practices

### ✅ Рекомендуется

-   использовать namespace для группировки (`site`, `features`, `integration`)
-   заполнять `description` для важных ключей
-   покрывать round-trip тестами (см. `tests/Feature/Admin/Options/OptionsApiTest.php`)
-   документировать новые ключи в `config/options.php` и /docs

### ❌ Избегайте

-   прямой работы с моделью без репозитория/хелпера
-   хранения чувствительных данных без шифрования
-   физического удаления записей (используйте soft delete + restore)

## Миграции значений

```php
public function up(): void
{
    $theme = options('theme', 'ui', []);
    $theme['dark_mode'] = false;

    option_set('theme', 'ui', $theme);
}
```

## Связанные материалы

-   `config/options.php` — allow-list namespace/key
-   `docs/_generated/api-docs/index.html` — Scribe API reference для `/api/v1/admin/options/*`
-   `docs/_generated/routes.md` и `docs/_generated/permissions.md` — артефакты после `composer docs:gen`
-   `docs/10-concepts/entries.md` — используйте entries вместо options для контента

---

> 💡 **Tip**: Options — для глобальных настроек. Для настроек конкретных записей используйте `Entry.data_json`.
