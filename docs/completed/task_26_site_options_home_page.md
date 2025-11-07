# Задача 26. Модель опций сайта (home page) — выполнено ✅

## Резюме

Реализована модель/репозиторий опций с удобными хелперами для чтения/записи значения `site:home_entry_id`. Изменение этой опции немедленно влияет на обработку корневого маршрута `/`.

## Реализованные компоненты

### 1. Модель Option
- **Файл**: `app/Models/Option.php`
- **Изменения**: Обновлена согласно спецификации (убраны статические методы)

### 2. OptionsRepository
- **Файл**: `app/Domain/Options/OptionsRepository.php`
- **Функционал**:
  - Кэширование опций с поддержкой тегов
  - Fallback для драйверов без поддержки тегов
  - Методы `get()`, `set()`, `getInt()`
  - Автоматическая инвалидация кэша при изменении

### 3. Хелперы
- **Файл**: `app/Helpers/options.php`
- **Функции**: `options()`, `option_set()`
- **Регистрация**: через `composer.json > autoload.files`

### 4. Событие OptionChanged
- **Файл**: `app/Events/OptionChanged.php`
- **Использование**: Отправляется при изменении опции для инвалидации кэша и аудита

### 5. HomeController
- **Файл**: `app/Http/Controllers/HomeController.php`
- **Маршрут**: `GET /`
- **Логика**: Рендерит опубликованную запись или дефолтный шаблон

### 6. CLI команды
- **Файлы**:
  - `app/Console/Commands/OptionsSetCommand.php`
  - `app/Console/Commands/OptionsGetCommand.php`
- **Команды**:
  - `php artisan cms:options:set {namespace} {key} {value?}`
  - `php artisan cms:options:get {namespace} {key}`

### 7. Валидация Admin API
- **Файл**: `app/Http/Requests/OptionsRequest.php`
- **Функционал**: Валидация для `site:home_entry_id` с проверкой существования записи

### 8. Тесты
- **Unit тесты**: `tests/Unit/OptionsRepositoryTest.php` (8 тестов)
- **Feature тесты**:
  - `tests/Feature/HomeControllerTest.php` (7 тестов)
  - `tests/Feature/OptionsCommandsTest.php` (5 тестов)
  - `tests/Feature/OptionsValidationTest.php` (5 тестов)
- **Результат**: Все 25 тестов проходят ✅

## Приёмка

- [x] Репозиторий и хелперы реализованы, зарегистрированы в контейнере
- [x] Событие `OptionChanged` отправляется, ResponseCache может инвалидироваться через листенер
- [x] Валидация установки опции не допускает несуществующих ID
- [x] Роут `/` корректно переключается между выбранной страницей и дефолтом
- [x] Все тесты из спецификации зелёные

## Документация

Полная документация: `docs/implemented/site_options_home_page.md`

## Пример использования

```php
// Установка главной страницы
$entry = Entry::published()->first();
option_set('site', 'home_entry_id', $entry->id);

// Чтение опции
$homeId = options('site', 'home_entry_id');
```

## CLI команды

```bash
# Установка опции
php artisan cms:options:set site home_entry_id 123

# Получение опции
php artisan cms:options:get site home_entry_id

# Сброс опции
php artisan cms:options:set site home_entry_id null
```

