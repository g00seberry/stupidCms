---
owner: "@backend-team"
system_of_record: "narrative"
review_cycle_days: 60
last_reviewed: 2025-11-08
related_code:
  - "app/Models/Option.php"
  - "app/Helpers/options.php"
  - "config/options.php"
---

# Options (настройки сайта)

**Options** — это key-value хранилище для настроек сайта в stupidCms.

## Концепция

### Зачем Options?

Некоторые настройки нужно менять **через админку**, а не через `.env` или config файлы:

- Название сайта
- Логотип
- Контактная информация
- Настройки интеграций (API ключи сторонних сервисов)
- Feature flags

Options — это база данных для таких настроек.

## Модель данных

**Таблица**: `options`

```php
Option {
  key: string (PK)
  value: json
  autoload: boolean         // загружать при старте приложения
  created_at: datetime
  updated_at: datetime
}
```

**Индексы**:
- `key` (PK)
- `autoload` — для быстрой загрузки

**Файл**: `app/Models/Option.php`

## Использование

### Установка значения

```php
use App\Models\Option;

Option::set('site_name', 'My Awesome CMS');
Option::set('contact_email', 'hello@example.com');
```

---

### Получение значения

```php
$siteName = Option::get('site_name'); // 'My Awesome CMS'
$email = Option::get('contact_email', 'default@example.com'); // с default
```

**Helper** (если создан в `app/Helpers/options.php`):

```php
$siteName = option('site_name');
$email = option('contact_email', 'default@example.com');
```

---

### Удаление

```php
Option::forget('old_setting');
```

---

### Проверка существования

```php
if (Option::has('feature_enabled')) {
    // ...
}
```

## Автозагрузка (autoload)

Options с `autoload = true` загружаются при старте приложения и кэшируются.

### Установка autoload

```php
Option::set('site_name', 'My CMS', autoload: true);
```

### Загрузка в сервис-провайдере

**Файл**: `app/Providers/AppServiceProvider.php`

```php
public function boot(): void
{
    $autoloadOptions = Option::where('autoload', true)->get();
    
    foreach ($autoloadOptions as $option) {
        config(["options.{$option->key}" => $option->value]);
    }
}
```

**Использование**:
```php
config('options.site_name'); // вместо Option::get()
```

## JSON значения

Options хранят значения как JSON, поэтому можно сохранять сложные структуры:

```php
Option::set('social_links', [
    'facebook' => 'https://facebook.com/mypage',
    'twitter' => 'https://twitter.com/mypage',
    'instagram' => 'https://instagram.com/mypage',
]);

$socials = Option::get('social_links');
// ['facebook' => '...', 'twitter' => '...']
```

## События

### OptionChanged

Триггерится при изменении option:

```php
// app/Events/OptionChanged.php

class OptionChanged
{
    public string $key;
    public mixed $oldValue;
    public mixed $newValue;
}
```

**Использование** (например, для инвалидации кэша):

```php
// app/Listeners/InvalidateConfigCache.php

public function handle(OptionChanged $event): void
{
    if ($event->key === 'site_name') {
        Cache::forget('site_metadata');
    }
}
```

## API

### Получение публичных настроек

**Endpoint**: `GET /api/options`

**Response**:
```json
{
  "data": {
    "site_name": "My Awesome CMS",
    "contact_email": "hello@example.com"
  }
}
```

> ⚠️ **Безопасность**: Возвращайте только публичные options (не API ключи!).

**Controller**:
```php
public function index()
{
    $public = ['site_name', 'contact_email', 'social_links'];
    
    $options = Option::whereIn('key', $public)->get()
        ->pluck('value', 'key');
    
    return response()->json(['data' => $options]);
}
```

---

### Обновление (admin)

**Endpoint**: `PUT /api/admin/options/{key}`

**Request**:
```json
{
  "value": "New Site Name"
}
```

**Response**:
```json
{
  "data": {
    "key": "site_name",
    "value": "New Site Name",
    "updated_at": "2025-11-08T12:00:00Z"
  }
}
```

## Примеры использования

### Feature Flags

```php
Option::set('feature_new_editor', true);

// В коде
if (option('feature_new_editor')) {
    return view('admin.editor.new');
} else {
    return view('admin.editor.old');
}
```

---

### Настройки интеграций

```php
Option::set('mailchimp_api_key', 'abc123...', autoload: false); // не autoload для безопасности

// В сервисе
$apiKey = Option::get('mailchimp_api_key');
$mailchimp = new MailchimpClient($apiKey);
```

---

### Настройки темы

```php
Option::set('theme', [
    'primary_color' => '#007bff',
    'font' => 'Inter',
    'logo_url' => '/media/logo.png',
]);

$theme = option('theme');
// ['primary_color' => '#007bff', ...]
```

## Группировка Options

Для удобства можно группировать options по префиксу:

```php
Option::set('theme.primary_color', '#007bff');
Option::set('theme.secondary_color', '#6c757d');
Option::set('smtp.host', 'smtp.gmail.com');
Option::set('smtp.port', 587);
```

**Получение группы**:

```php
$themeOptions = Option::where('key', 'LIKE', 'theme.%')->get();
```

Или создать метод в модели:

```php
// app/Models/Option.php

public static function group(string $prefix): Collection
{
    return static::where('key', 'LIKE', "{$prefix}.%")
        ->get()
        ->mapWithKeys(fn($opt) => [
            str_replace("{$prefix}.", '', $opt->key) => $opt->value
        ]);
}
```

Использование:

```php
$theme = Option::group('theme');
// ['primary_color' => '#007bff', 'secondary_color' => '#6c757d']
```

## Кэширование

### Кэш всех options

```php
$options = Cache::remember('options', 3600, fn() =>
    Option::all()->pluck('value', 'key')
);
```

### Инвалидация при изменении

```php
// app/Observers/OptionObserver.php

public function saved(Option $option): void
{
    Cache::forget('options');
    event(new OptionChanged($option->key, $option->getOriginal('value'), $option->value));
}
```

## Безопасность

### Не храните чувствительные данные

❌ **Плохо**:
```php
Option::set('database_password', 'secret');
```

✅ **Хорошо**:
```env
DB_PASSWORD=secret  # в .env
```

### Шифрование (если необходимо)

Для API ключей используйте Laravel Crypt:

```php
use Illuminate\Support\Facades\Crypt;

Option::set('stripe_secret_key', Crypt::encryptString($key));

// Получение
$key = Crypt::decryptString(Option::get('stripe_secret_key'));
```

## Best Practices

### ✅ DO

- Используйте `autoload: true` для часто используемых options
- Группируйте options по префиксам (`theme.*`, `smtp.*`)
- Кэшируйте options
- Документируйте доступные options в коде или админке

### ❌ DON'T

- Не храните чувствительные данные в открытом виде
- Не используйте options для данных, которые должны быть в БД (например, настройки конкретного entry)
- Не создавайте десятки options — группируйте в JSON

## Миграция значений

Если нужно изменить структуру option:

```php
// database/migrations/2025_11_08_update_theme_option.php

public function up()
{
    $theme = Option::get('theme');
    
    // Добавить новое поле
    $theme['dark_mode'] = false;
    
    Option::set('theme', $theme);
}
```

## Связанные страницы

- [Config Reference](../30-reference/config.md) — конфигурация приложения
- [Entries](entries.md) — для динамических данных используйте entries, а не options

---

> 💡 **Tip**: Options — для глобальных настроек сайта. Для настроек конкретных entries используйте `Entry.data_json`.

