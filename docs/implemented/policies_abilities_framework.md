# Политики/Abilities каркас

## Резюме

Реализована базовая система авторизации на Laravel 12 с использованием Gate и Policies. Система обеспечивает глобальный доступ для администраторов через `Gate::before()` и политики для моделей `Entry`, `Term`, `Media` с типовыми и кастомными abilities.

**Дата реализации:** 2025-11-07

---

## Структура системы

### 1. Модель ролей

Минимальная модель ролей основана на признаке администратора в таблице `users`:

-   Поле: `users.is_admin TINYINT(1) DEFAULT 0`
-   В модели `User` — геттер `$user->is_admin` и cast в boolean

### 2. Миграция

**Файл:** `database/migrations/2025_11_07_052620_add_is_admin_to_users_table.php`

Добавляет поле `is_admin` типа boolean с значением по умолчанию `false` после поля `password`.

### 3. Модель User

**Файл:** `app/Models/User.php`

Добавлены:

-   Cast для `is_admin` в boolean (cast автоматически преобразует значение в boolean)
-   Защита от mass assignment: `$guarded = ['is_admin']` и явный `$fillable` для предотвращения эскалации привилегий

### 4. AuthServiceProvider

**Файл:** `app/Providers/AuthServiceProvider.php`

Реализовано:

-   Регистрация политик для моделей `Entry`, `Term`, `Media`
-   `Gate::before()` для глобального доступа администраторам
-   Метод `registerPolicies()` для явной регистрации политик

### 5. Политики (Policies)

#### EntryPolicy

**Файл:** `app/Policies/EntryPolicy.php`

**Типовые abilities:**

-   `viewAny(User)` — просмотр списка записей
-   `view(User, Entry)` — просмотр конкретной записи
-   `create(User)` — создание записи
-   `update(User, Entry)` — обновление записи
-   `delete(User, Entry)` — удаление записи
-   `restore(User, Entry)` — восстановление записи
-   `forceDelete(User, Entry)` — физическое удаление

**Кастомные abilities:**

-   `publish(User, Entry)` — публикация/снятие с публикации
-   `attachMedia(User, Entry)` — управление связями медиаданных
-   `manageTerms(User, Entry)` — добавление/удаление терминов

#### TermPolicy

**Файл:** `app/Policies/TermPolicy.php`

**Типовые abilities:**

-   `viewAny(User)` — просмотр списка терминов
-   `view(User, Term)` — просмотр конкретного термина
-   `create(User)` — создание термина
-   `update(User, Term)` — обновление термина
-   `delete(User, Term)` — удаление термина
-   `restore(User, Term)` — восстановление термина
-   `forceDelete(User, Term)` — физическое удаление

**Кастомные abilities:**

-   `attachEntry(User, Term)` — привязка записей к термину

#### MediaPolicy

**Файл:** `app/Policies/MediaPolicy.php`

**Типовые abilities:**

-   `viewAny(User)` — просмотр списка медиа
-   `view(User, Media)` — просмотр конкретного медиа
-   `create(User)` — создание медиа (загрузка)
-   `update(User, Media)` — обновление медиа
-   `delete(User, Media)` — удаление медиа
-   `restore(User, Media)` — восстановление медиа
-   `forceDelete(User, Media)` — физическое удаление

**Кастомные abilities:**

-   `upload(User)` — загрузка медиа (проверяется на уровне класса `Media::class`, без привязки к конкретному инстансу)
-   `reprocess(User, Media)` — пересборка деривативов
-   `move(User, Media)` — перемещение между стораджами/папками

> **Важно:** Все методы политик по умолчанию возвращают `false` для не-админов. Благодаря `Gate::before()` администратор получает `true` на любой ability без изменения этих классов.

---

## Точки вызова авторизации

### Контроллеры

Для resource контроллеров рекомендуется использовать:

```php
class EntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(\App\Models\Entry::class, 'entry');
    }

    public function publish(Entry $entry)
    {
        $this->authorize('publish', $entry);
        // ...
    }
}
```

### FormRequest

В FormRequest классах:

```php
class UpdateEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('entry');
        return $this->user()?->can('update', $entry) ?? false;
    }
}
```

### Blade/Frontend

В Blade шаблонах:

```blade
@can('update', $entry)
  <x-button>Edit</x-button>
@endcan
```

---

## Статусы ответов

-   **Гость (неаутентифицирован)** → middleware `auth:*` вернёт **401 Unauthorized**
-   **Аутентифицирован, но без прав** → `AuthorizationException` => **403 Forbidden**

---

## Утилиты

### Команда назначения администратора

**Файл:** `app/Console/Commands/UserMakeAdminCommand.php`

**Использование:**

```bash
php artisan user:make-admin user@example.com
```

Команда:

-   Находит пользователя по email
-   Устанавливает `is_admin=1`
-   Выводит сообщение об успехе или ошибке

### UserFactory

**Файл:** `database/factories/UserFactory.php`

Добавлен метод `admin()` для создания администраторов в тестах:

```php
$admin = User::factory()->admin()->create();
```

---

## Тесты

**Файл:** `tests/Feature/AuthorizationTest.php`

Набор тестов покрывает:

1. **Админ**: полный доступ ко всем abilities для Entry/Term/Media
2. **Обычный пользователь**: все abilities возвращают `false`
3. **Гость**: все abilities возвращают `false`
4. **UserFactory**: метод `admin()` корректно создаёт администраторов
5. **User модель**: геттер `is_admin` работает корректно
6. **Политики**: проверка регистрации политик
7. **HTTP-уровень**: проверка 403 для гостя и обычного пользователя, 200 для администратора на защищённых маршрутах

Все тесты проходят успешно (13 passed, 72 assertions).

---

## Принцип работы

### Gate::before()

```php
Gate::before(function (User $user, string $ability) {
    return $user->is_admin ? true : null; // null => продолжить обычные проверки
});
```

Этот callback выполняется перед проверкой любой политики:

-   Если пользователь — администратор (`is_admin = true`), возвращается `true` и дальнейшие проверки не выполняются
-   Если пользователь не администратор, возвращается `null`, и выполняется обычная проверка через политику

### Политики

Все методы политик возвращают `false` по умолчанию. Это означает, что:

-   Для администраторов: `Gate::before()` вернёт `true` до вызова политики
-   Для обычных пользователей: политика вернёт `false`, доступ запрещён

---

## Расширяемость

Система спроектирована для дальнейшего расширения:

1. **Ролевая модель**: можно заменить каркас `false` на ролевую модель (таблицы `roles`, `permissions`) и провайдер прав
2. **Fast-path для супер-админа**: `Gate::before()` можно оставить как fast-path для супер-админа при миграции
3. **Кэширование**: для производительности можно кэшировать полномочия пользователя (например, cache tags `user:perm:{id}`)

---

## Файлы изменений

### Новые файлы

-   `database/migrations/2025_11_07_052620_add_is_admin_to_users_table.php`
-   `tests/Feature/AuthorizationTest.php`
-   `docs/implemented/policies_abilities_framework.md`

### Изменённые файлы

-   `app/Models/User.php` — добавлен cast и геттер для `is_admin`
-   `app/Providers/AuthServiceProvider.php` — регистрация политик и `Gate::before()`
-   `app/Policies/EntryPolicy.php` — добавлены кастомные методы
-   `app/Policies/TermPolicy.php` — добавлен кастомный метод
-   `app/Policies/MediaPolicy.php` — добавлены кастомные методы
-   `app/Console/Commands/UserMakeAdminCommand.php` — реализована команда
-   `database/factories/UserFactory.php` — добавлен метод `admin()`

---

## Критерии приёмки

✅ Администратор имеет полный доступ ко всем abilities без исключений  
✅ Пользователь без прав получает `false` на все abilities  
✅ Гость получает 403 Forbidden на защищённых HTTP-маршрутах  
✅ Политики зарегистрированы и работают корректно  
✅ Команда `user:make-admin` работает корректно  
✅ UserFactory поддерживает создание администраторов  
✅ Защита от mass assignment: `is_admin` в `$guarded`  
✅ Все тесты проходят успешно

## Исправления после ревью

После первоначального ревью были внесены следующие исправления:

1. **Защита от mass assignment**:

    - Убран `$guarded = []`
    - Добавлен явный `$fillable` с разрешёнными полями
    - `is_admin` добавлен в `$guarded` для предотвращения эскалации привилегий

2. **Сигнатура `MediaPolicy::upload()`**:

    - Использует консистентный стиль: `upload(User $user)` — проверка на уровне класса
    - Вызывается через `Gate::allows('upload', Media::class)`

3. **HTTP-тесты на 403**:

    - Добавлены тесты `test_guest_receives_403_on_protected_route()`
    - Добавлены тесты `test_regular_user_receives_403_on_protected_route()`
    - Добавлены тесты `test_admin_receives_200_on_protected_route()`
    - Создан тестовый маршрут `/test/admin/entries` с middleware `can:viewAny,Entry`

4. **Геттер `is_admin`**:
    - Убран дублирующий геттер `getIsAdminAttribute()`, оставлен только cast
    - Cast автоматически преобразует значение в boolean, что упрощает код

---

## Примечания

-   Система является каркасом и готова к дальнейшему развитию
-   Для тонкой грануляции прав потребуется дополнительная реализация ролевой модели
-   Все abilities по умолчанию запрещены для не-админов, что обеспечивает безопасность по умолчанию
