# Система зарезервированных маршрутов

## Обзор

Система предотвращает создание страниц с URL, которые конфликтуют с системными маршрутами (например, `admin`, `api`). Состоит из реестра маршрутов (`ReservedRouteRegistry`) и правила валидации (`NotReservedRoute`).

## Структура

```
app/
├── Domain/Pages/Validation/
│   └── NotReservedRoute.php          # Правило валидации Laravel
├── Support/ReservedRoutes/
│   └── ReservedRouteRegistry.php     # Реестр зарезервированных маршрутов
└── Providers/
    └── ReservedRoutesServiceProvider.php  # Регистрация в DI

config/
└── stupidcms.php                     # Конфигурация маршрутов

lang/ru/
└── validation.php                    # Сообщения об ошибках
```

## Принцип работы

### 1. ReservedRouteRegistry

Центральный класс, который:
- Загружает маршруты из **конфига** (`config/stupidcms.php`) и **БД** (таблица `reserved_routes`)
- Кэширует результаты на 60 секунд
- Нормализует пути (trim слэшей/пробелов, lowercase, NFC)

**Типы маршрутов:**
- `paths` — точное совпадение (например, `admin` для `/admin`)
- `prefixes` — префикс для подпутей (например, `api` для `/api/*`)

**Методы:**
- `isReservedPath(string $path): bool` — проверка точного совпадения
- `isReservedPrefix(string $path): bool` — проверка префикса
- `isReservedSlug(string $slug): bool` — проверка slug (первый сегмент), проверяет и `paths`, и `prefixes`

### 2. NotReservedRoute

Правило валидации Laravel, которое:
- Использует `ReservedRouteRegistry` для проверки
- Нормализует входной slug перед проверкой
- Возвращает локализованное сообщение об ошибке

### 3. Загрузка данных

```php
// Из конфига
'reserved_routes' => [
    'paths' => ['admin'],
    'prefixes' => ['admin', 'api'],
]

// Из БД (таблица reserved_routes)
ReservedRoute::create([
    'path' => 'custom',
    'kind' => 'prefix',  // или 'path'
    'source' => 'plugin',
]);
```

Данные из конфига и БД **объединяются** и дедуплицируются.

## Использование

### В FormRequest

```php
use App\Domain\Pages\Validation\NotReservedRoute;

public function rules(): array
{
    return [
        'slug' => [
            'required',
            'string',
            'max:120',
            new NotReservedRoute(app(ReservedRouteRegistry::class)),
        ],
    ];
}
```

### В контроллере

```php
use App\Domain\Pages\Validation\NotReservedRoute;
use App\Support\ReservedRoutes\ReservedRouteRegistry;

$request->validate([
    'slug' => [
        'required',
        'string',
        new NotReservedRoute(app(ReservedRouteRegistry::class)),
    ],
]);
```

## Нормализация

Все пути нормализуются одинаково:
1. Trim слэшей, пробелов, табов (`trim($path, " \t\n\r\0\x0B/\\")`)
2. NFC нормализация (если доступно расширение `intl`)
3. Приведение к нижнему регистру (`mb_strtolower`)

**Примеры:**
- `"/admin"` → `"admin"`
- `"Admin"` → `"admin"`
- `" admin "` → `"admin"`

## Формат ошибки

При нарушении возвращается:

**HTTP 422**
```json
{
  "message": "Данные не прошли валидацию.",
  "errors": {
    "slug": ["Значение поля slug конфликтует с зарезервированными маршрутами (например: admin, api)."]
  }
}
```

## Кэширование

- Кэш хранится 60 секунд (константа `CACHE_TTL`)
- Ключ: `reserved_routes_all`
- Очистка: `$registry->clearCache()`

**Важно:** После добавления маршрута в БД нужно очистить кэш, иначе изменения не применятся.

## Тестирование

Unit-тесты:
- `tests/Unit/NotReservedRouteRuleTest.php` — тесты правила валидации
- `tests/Unit/ReservedRouteRegistryTest.php` — тесты реестра

Покрытие:
- Проверка зарезервированных slug'ов
- Нормализация (case-insensitive, trim)
- Загрузка из конфига и БД
- Кэширование

