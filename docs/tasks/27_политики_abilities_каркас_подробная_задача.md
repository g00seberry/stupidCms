# Задача 27. Политики/Abilities каркас

## Резюме
Построить базовую систему авторизации на **Laravel 12**:
- Глобальный доступ для администратора через `Gate::before()`.
- Политики (Policies) для моделей: `Entry`, `Term`, `Media` с типовыми и кастомными abilities.
- Единые места вызова авторизации: контроллеры, FormRequest, Blade.
- Правильные HTTP-ответы: 401 для неаутентифицированных, 403 для аутентифицированных без прав.

**Критерии приёмки:**
- Администратор имеет полный доступ ко всем abilities без исключений.
- Пользователь без прав получает **403 Forbidden** на защищённых действиях.

---

## Минимальная модель ролей
Для каркаса достаточно признака администратора в таблице `users`:
- Поле: `users.is_admin TINYINT(1) DEFAULT 0`.
- В `User` — геттер `$user->is_admin`.
> Далее можно эволюционировать к ролям/правам (напр., spatie/laravel-permission), но сейчас — только `is_admin`.

### Миграция (пример)
```php
Schema::table('users', function (Blueprint $t) {
    $t->boolean('is_admin')->default(false)->after('password');
});
```

---

## Каркас Gate и регистрация Policy
`App\Providers\AuthServiceProvider`:
```php
namespace App\Providers;

use App\Models\{Entry, Term, Media, User};
use App\Policies\{EntryPolicy, TermPolicy, MediaPolicy};
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        Entry::class => EntryPolicy::class,
        Term::class  => TermPolicy::class,
        Media::class => MediaPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies(); // или rely on auto-discovery, но явно — надёжнее

        Gate::before(function (User $user, string $ability) {
            // Полный доступ администратору
            return $user->is_admin ? true : null; // null => продолжить обычные проверки
        });
    }
}
```

---

## Набор abilities
### Общие для Eloquent-ресурсов
- `viewAny(User)` — список
- `view(User, Model)` — просмотр
- `create(User)` — создание
- `update(User, Model)` — изменение
- `delete(User, Model)` — удаление
- `restore(User, Model)` — восстановление
- `forceDelete(User, Model)` — физическое удаление

### Кастомные
- Для `Entry`:
  - `publish(User, Entry)` — публикация/снятие.
  - `attachMedia(User, Entry)` — управление связями медиаданных.
  - `manageTerms(User, Entry)` — добавление/удаление терминов.
- Для `Term`:
  - `attachEntry(User, Term)` — привязка записей к термину.
- Для `Media`:
  - `upload(User)` — загрузка
  - `reprocess(User, Media)` — пересборка деривативов
  - `move(User, Media)` — перемещение между стораджами/папками

> По умолчанию для не-админов в каркасе возвращаем `false`. Тонкая грануляция появится в задачах роли/пермишены.

---

## Реализация Policy (скелеты)
### `app/Policies/EntryPolicy.php`
```php
namespace App\Policies;

use App\Models\{Entry, User};

class EntryPolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, Entry $entry): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, Entry $entry): bool { return false; }
    public function delete(User $user, Entry $entry): bool { return false; }
    public function restore(User $user, Entry $entry): bool { return false; }
    public function forceDelete(User $user, Entry $entry): bool { return false; }

    // Кастомные
    public function publish(User $user, Entry $entry): bool { return false; }
    public function attachMedia(User $user, Entry $entry): bool { return false; }
    public function manageTerms(User $user, Entry $entry): bool { return false; }
}
```

### `app/Policies/TermPolicy.php`
```php
namespace App\Policies;

use App\Models\{Term, User};

class TermPolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, Term $term): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, Term $term): bool { return false; }
    public function delete(User $user, Term $term): bool { return false; }
    public function restore(User $user, Term $term): bool { return false; }
    public function forceDelete(User $user, Term $term): bool { return false; }

    // Кастомные
    public function attachEntry(User $user, Term $term): bool { return false; }
}
```

### `app/Policies/MediaPolicy.php`
```php
namespace App\Policies;

use App\Models\{Media, User};

class MediaPolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, Media $media): bool { return false; }
    public function create(User $user): bool { return false; } // = upload
    public function update(User $user, Media $media): bool { return false; }
    public function delete(User $user, Media $media): bool { return false; }
    public function restore(User $user, Media $media): bool { return false; }
    public function forceDelete(User $user, Media $media): bool { return false; }

    // Кастомные
    public function upload(User $user): bool { return false; }
    public function reprocess(User $user, Media $media): bool { return false; }
    public function move(User $user, Media $media): bool { return false; }
}
```
> Благодаря `Gate::before()` админ получает `true` на **любой** ability без изменения этих классов.

---

## Точки вызова авторизации
### Контроллеры (resource контроллеры)
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
```blade
@can('update', $entry)
  <x-button>Edit</x-button>
@endcan
```

---

## Статусы ответов
- Гость (неаутентифицирован) → middleware `auth:*` вернёт **401 Unauthorized**.
- Аутентифицирован, но без прав → `AuthorizationException` => **403 Forbidden**.

Для API можно настроить единый JSON формат ошибок (RFC7807) в `App\Exceptions\Handler` — вне рамок каркаса.

---

## Тесты (PHPUnit)
### Фабрики
```php
// database/factories/UserFactory.php
public function admin(): self { return $this->state(fn()=>['is_admin'=>true]); }
```

### Набор тестов
1. **Админ**: `actingAs(admin)` может `viewAny/create/update/delete/publish` для Entry/Term/Media → 200/201.
2. **Обычный пользователь**: `actingAs(user)` на защищённые действия → **403**.
3. **Гость**: без аутентификации любые защищённые эндпоинты → **401**.
4. **FormRequest**: `authorize()` возвращает false для non-admin → 403.
5. **Политики подключены**: вызов `$this->authorize('update', $entry)` дергает `EntryPolicy@update` (можно замокать Gate и assert called).

---

## Команды/утилиты
- Сгенерировать политики:
```bash
php artisan make:policy EntryPolicy --model=Entry
php artisan make:policy TermPolicy  --model=Term
php artisan make:policy MediaPolicy --model=Media
```
- Утилита для назначения админа:
```bash
php artisan user:make-admin user@example.com
```
(Команда выставляет `is_admin=1`.)

---

## Приёмка (Definition of Done)
- [ ] `AuthServiceProvider` зарегистрирован, `Gate::before()` возвращает `true` для админа.
- [ ] Созданы и подключены `EntryPolicy`, `TermPolicy`, `MediaPolicy` с указанными abilities.
- [ ] Контроллеры используют `authorizeResource()` и точечные `$this->authorize()` для кастомных действий.
- [ ] Для неадминов защищённые операции завершаются 403.
- [ ] Написаны и зелёные тесты для сценариев admin/regular/guest.

---

## Примечания по расширяемости
- Позднее заменить каркас `false` на ролевую модель (таблицы `roles`, `permissions`) и провайдер прав.
- При миграции — оставить `Gate::before()` как fast-path для супер-админа.
- Для производительности — кэшировать полномочия пользователя (например, cache tags `user:perm:{id}`).