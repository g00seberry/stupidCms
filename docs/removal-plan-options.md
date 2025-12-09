# План удаления системы Options

**Цель:** Полностью удалить систему опций (`options`) из CMS.

**Статус:** План создания

---

## Обзор системы Options

Система Options предоставляет хранение настроек в формате ключ-значение с поддержкой пространств имён (`namespace`).

### Текущее использование

1. **`site:home_entry_id`** — используется в `HomeController` для определения главной страницы
2. **Конфигурация:** `config/options.php` — allow-list опций
3. **API:** CRUD операции через `/api/v1/admin/options/{namespace}/{key}`
4. **CLI:** команды `cms:options:get` и `cms:options:set`

---

## Шаги удаления

### 1. Анализ зависимостей

#### 1.1. Использование в коде

**Файлы, использующие Options:**

- `app/Http/Controllers/HomeController.php` — читает `site:home_entry_id`
- `app/Helpers/options.php` — helper функции `options()` и `option_set()`
- `config/options.php` — конфигурация allow-list

**Потенциальные зависимости:**

- Проверить использование `options()` и `option_set()` в коде
- Проверить импорты `OptionsRepository`, `Option`, `OptionPolicy`
- Проверить упоминания `options.allowed` в конфигах

#### 1.2. Зависимости от Options

**API эндпоинты:**
- `GET /api/v1/admin/options/{namespace}` — список опций
- `GET /api/v1/admin/options/{namespace}/{key}` — получить опцию
- `PUT /api/v1/admin/options/{namespace}/{key}` — создать/обновить
- `DELETE /api/v1/admin/options/{namespace}/{key}` — удалить
- `POST /api/v1/admin/options/{namespace}/{key}/restore` — восстановить

**CLI команды:**
- `php artisan cms:options:get {namespace} {key}`
- `php artisan cms:options:set {namespace} {key} {value?}`

**Модель и репозиторий:**
- `app/Models/Option.php`
- `app/Domain/Options/OptionsRepository.php`

**Контроллеры и запросы:**
- `app/Http/Controllers/Admin/V1/OptionsController.php`
- `app/Http/Requests/Admin/Options/IndexOptionsRequest.php`
- `app/Http/Requests/Admin/Options/PutOptionRequest.php`
- `app/Http/Requests/OptionsRequest.php`

**Ресурсы:**
- `app/Http/Resources/Admin/OptionResource.php`
- `app/Http/Resources/Admin/OptionCollection.php`

**События:**
- `app/Events/OptionChanged.php`

**Политики:**
- `app/Policies/OptionPolicy.php`

**Тесты:**
- `tests/Feature/Api/Options/OptionsTest.php`
- `tests/Feature/Options/OptionsRepositoryTest.php`
- `tests/Feature/Models/OptionTest.php`
- `tests/Unit/Models/OptionTest.php`

**Другое:**
- `app/Providers/AppServiceProvider.php` — регистрация `OptionsRepository`
- `app/Providers/AuthServiceProvider.php` — регистрация `OptionPolicy`
- `bootstrap/app.php` — регистрация CLI команд
- `routes/api_admin.php` — маршруты API
- `database/factories/OptionFactory.php`
- `config/options.php`

---

### 2. Решение проблем зависимостей

#### 2.1. Замена `site:home_entry_id` в `HomeController`

**Проблема:** `HomeController` использует `options('site', 'home_entry_id')` для определения главной страницы.

**Варианты решения:**

1. **Убрать функциональность главной страницы** — всегда показывать дефолтный шаблон
2. **Перенести в конфиг** — использовать `config('app.home_entry_id')`
3. **Использовать RouteNode** — после внедрения иерархической маршрутизации

**Рекомендация:** Вариант 1 (упростить, убрать динамическую главную страницу). Если нужна будет главная страница — реализовать через RouteNode.

**Файлы для изменения:**
- `app/Http/Controllers/HomeController.php` — убрать чтение опции, всегда возвращать дефолтный шаблон

---

### 3. Удаление компонентов

#### 3.1. Удалить модели и репозитории

**Файлы:**
- `app/Models/Option.php`
- `app/Domain/Options/OptionsRepository.php`

**Проверить:**
- Нет импортов `Option` и `OptionsRepository` в других файлах
- Нет использования в событиях/слушателях

#### 3.2. Удалить контроллеры и запросы

**Файлы:**
- `app/Http/Controllers/Admin/V1/OptionsController.php`
- `app/Http/Requests/Admin/Options/IndexOptionsRequest.php`
- `app/Http/Requests/Admin/Options/PutOptionRequest.php`
- `app/Http/Requests/OptionsRequest.php`

#### 3.3. Удалить ресурсы

**Файлы:**
- `app/Http/Resources/Admin/OptionResource.php`
- `app/Http/Resources/Admin/OptionCollection.php`

#### 3.4. Удалить события

**Файлы:**
- `app/Events/OptionChanged.php`

**Проверить:**
- Нет слушателей события `OptionChanged`

#### 3.5. Удалить политики

**Файлы:**
- `app/Policies/OptionPolicy.php`

**Изменения:**
- Удалить из `app/Providers/AuthServiceProvider.php`:
  ```php
  Option::class => OptionPolicy::class,
  ```

#### 3.6. Удалить CLI команды

**Файлы:**
- `app/Console/Commands/OptionsGetCommand.php`
- `app/Console/Commands/OptionsSetCommand.php`

**Изменения:**
- Удалить из `bootstrap/app.php`:
  ```php
  App\Console\Commands\OptionsGetCommand::class,
  App\Console\Commands\OptionsSetCommand::class,
  ```

#### 3.7. Удалить хелперы

**Файлы:**
- `app/Helpers/options.php`

**Проверить:**
- Нет вызовов `options()` и `option_set()` в коде

#### 3.8. Удалить конфигурацию

**Файлы:**
- `config/options.php`

#### 3.9. Удалить маршруты

**Файлы:**
- `routes/api_admin.php` — удалить группу `/options`

**Изменения:**
```php
// Удалить:
Route::prefix('/options')->group(function () {
    Route::get('/{namespace}', [OptionsController::class, 'index'])...
    Route::get('/{namespace}/{key}', [OptionsController::class, 'show'])...
    Route::put('/{namespace}/{key}', [OptionsController::class, 'put'])...
    Route::delete('/{namespace}/{key}', [OptionsController::class, 'destroy'])...
    Route::post('/{namespace}/{key}/restore', [OptionsController::class, 'restore'])...
});

// Удалить импорт:
use App\Http\Controllers\Admin\V1\OptionsController;
```

#### 3.10. Удалить регистрацию сервисов

**Файлы:**
- `app/Providers/AppServiceProvider.php`

**Изменения:**
```php
// Удалить регистрацию OptionsRepository:
$this->app->singleton(OptionsRepository::class, function ($app) {
    return new OptionsRepository($app->make(CacheRepository::class));
});

// Удалить импорт:
use App\Domain\Options\OptionsRepository;
```

#### 3.11. Удалить миграции и фабрики

**Файлы:**
- `database/factories/OptionFactory.php`

**Примечание:** Миграцию не удалять, если уже применена в продакшене. Создать новую миграцию для удаления таблицы.

#### 3.12. Удалить тесты

**Файлы:**
- `tests/Feature/Api/Options/OptionsTest.php`
- `tests/Feature/Options/OptionsRepositoryTest.php`
- `tests/Feature/Models/OptionTest.php`
- `tests/Unit/Models/OptionTest.php`

---

### 4. Миграция базы данных

#### 4.1. Удалить исходную миграцию создания таблицы

**Файл:** `database/migrations/2025_11_06_000050_create_options_table.php`

Удалить исходную миграцию создания таблицы полностью. Не создавать корректирующую миграцию для удаления таблицы — работать только с целевыми миграциями.

---

### 5. Обновление документации

**Файлы:**
- Удалить упоминания Options из `docs/routing-system.md` (если есть)
- Обновить `docs/generated/README.md` (перегенерировать)
- Обновить API документацию (Scribe)

---

### 6. Чек-лист выполнения

#### 6.1. Подготовка

- [ ] Найти все использования `options()` и `option_set()` в коде
- [ ] Найти все импорты `Option`, `OptionsRepository`, `OptionPolicy`
- [ ] Проверить использование в тестах
- [ ] Проверить наличие слушателей события `OptionChanged`

#### 6.2. Замена зависимостей

- [ ] Обновить `HomeController` — убрать использование `options('site', 'home_entry_id')`
- [ ] Убедиться, что нет других использований Options в коде

#### 6.3. Удаление файлов

- [ ] Удалить модель `app/Models/Option.php`
- [ ] Удалить репозиторий `app/Domain/Options/OptionsRepository.php`
- [ ] Удалить контроллер `app/Http/Controllers/Admin/V1/OptionsController.php`
- [ ] Удалить запросы (`IndexOptionsRequest`, `PutOptionRequest`, `OptionsRequest`)
- [ ] Удалить ресурсы (`OptionResource`, `OptionCollection`)
- [ ] Удалить событие `app/Events/OptionChanged.php`
- [ ] Удалить политику `app/Policies/OptionPolicy.php`
- [ ] Удалить CLI команды (`OptionsGetCommand`, `OptionsSetCommand`)
- [ ] Удалить хелперы `app/Helpers/options.php`
- [ ] Удалить конфигурацию `config/options.php`
- [ ] Удалить фабрику `database/factories/OptionFactory.php`

#### 6.4. Обновление регистраций

- [ ] Удалить маршруты из `routes/api_admin.php`
- [ ] Удалить регистрацию `OptionsRepository` из `AppServiceProvider`
- [ ] Удалить регистрацию `OptionPolicy` из `AuthServiceProvider`
- [ ] Удалить регистрацию команд из `bootstrap/app.php`

#### 6.5. Удаление тестов

- [ ] Удалить `tests/Feature/Api/Options/OptionsTest.php`
- [ ] Удалить `tests/Feature/Options/OptionsRepositoryTest.php`
- [ ] Удалить `tests/Feature/Models/OptionTest.php`
- [ ] Удалить `tests/Unit/Models/OptionTest.php`

#### 6.6. База данных

- [ ] Удалить исходную миграцию создания таблицы `2025_11_06_000050_create_options_table.php`
- [ ] Примечание: не создавать корректирующую миграцию, работать с целевыми миграциями

#### 6.7. Финальная проверка

- [ ] Запустить все тесты: `php artisan test`
- [ ] Проверить отсутствие ошибок линтера
- [ ] Перегенерировать документацию: `php artisan docs:generate`
- [ ] Перегенерировать API документацию: `php artisan scribe:gen`
- [ ] Проверить, что нет упоминаний Options в коде: `grep -r "Option\|options" app/ --exclude-dir=vendor`

---

### 7. Порядок выполнения

**Рекомендуемый порядок:**

1. **Обновить зависимости** (заменить использование Options в `HomeController`)
2. **Удалить маршруты и регистрации** (чтобы API сразу перестал работать)
3. **Удалить файлы по порядку:**
   - Контроллеры и запросы
   - Ресурсы
   - Политики и события
   - CLI команды
   - Хелперы
   - Репозитории и модели
   - Конфигурация
4. **Удалить тесты**
5. **Создать и выполнить миграцию**
6. **Обновить документацию**
7. **Финальная проверка**

---

## Примечания

- **Опция `site:home_entry_id`:** После удаления Options главная страница всегда будет показывать дефолтный шаблон `home.default`. Если нужна будет динамическая главная страница — реализовать через RouteNode после внедрения иерархической маршрутизации.

- **Кэш опций:** После удаления Options необходимо очистить кэш опций (если использовался cache tags `['options', 'options:*']`).

- **Breaking changes:** Удаление Options — breaking change для API. Убедиться, что фронтенд/клиенты не используют эти эндпоинты.

---

**Дата создания:** 2025-12-09  
**Автор:** Автоматически сгенерировано

