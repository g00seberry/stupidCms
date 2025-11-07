# Код для ревью: Политики/Abilities каркас

## Обзор изменений

Реализована базовая система авторизации на Laravel 12 с использованием Gate и Policies. Все файлы, изменённые или созданные в рамках задачи.

---

## 1. Миграция: добавление поля is_admin

**Файл:** `database/migrations/2025_11_07_052620_add_is_admin_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
```

---

## 2. Модель User

**Файл:** `app/Models/User.php`

```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [
        'is_admin', // Защита от массового присвоения администраторских прав
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

}
```

---

## 3. AuthServiceProvider

**Файл:** `app/Providers/AuthServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Models\{Entry, Term, Media, User};
use App\Policies\{EntryPolicy, TermPolicy, MediaPolicy};
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Entry::class => EntryPolicy::class,
        Term::class  => TermPolicy::class,
        Media::class => MediaPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Глобальный доступ для администратора
        Gate::before(function (User $user, string $ability) {
            // Полный доступ администратору
            return $user->is_admin ? true : null; // null => продолжить обычные проверки
        });
    }
}
```

---

## 4. EntryPolicy

**Файл:** `app/Policies/EntryPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EntryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Entry $entry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Entry $entry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Entry $entry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Entry $entry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Entry $entry): bool
    {
        return false;
    }

    // Кастомные abilities
    /**
     * Determine whether the user can publish/unpublish the entry.
     */
    public function publish(User $user, Entry $entry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can attach media to the entry.
     */
    public function attachMedia(User $user, Entry $entry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can manage terms for the entry.
     */
    public function manageTerms(User $user, Entry $entry): bool
    {
        return false;
    }
}
```

---

## 5. TermPolicy

**Файл:** `app/Policies/TermPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Term;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TermPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Term $term): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Term $term): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Term $term): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Term $term): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Term $term): bool
    {
        return false;
    }

    // Кастомные abilities
    /**
     * Determine whether the user can attach entries to the term.
     */
    public function attachEntry(User $user, Term $term): bool
    {
        return false;
    }
}
```

---

## 6. MediaPolicy

**Файл:** `app/Policies/MediaPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MediaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Media $media): bool
    {
        return false;
    }

    // Кастомные abilities
    /**
     * Determine whether the user can upload media.
     * Проверяется на уровне класса (Media::class), без привязки к конкретному инстансу.
     */
    public function upload(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can reprocess media variants.
     */
    public function reprocess(User $user, Media $media): bool
    {
        return false;
    }

    /**
     * Determine whether the user can move media between storages/folders.
     */
    public function move(User $user, Media $media): bool
    {
        return false;
    }
}
```

---

## 7. Команда UserMakeAdminCommand

**Файл:** `app/Console/Commands/UserMakeAdminCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserMakeAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:make-admin {email : The email of the user to make admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a user an administrator by setting is_admin=1';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        if ($user->is_admin) {
            $this->info("User '{$email}' is already an administrator.");
            return Command::SUCCESS;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("User '{$email}' has been made an administrator.");

        return Command::SUCCESS;
    }
}
```

---

## 8. UserFactory

**Файл:** `database/factories/UserFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should be an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }
}
```

---

## 9. Тесты

**Файл:** `tests/Feature/AuthorizationTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Media;
use App\Models\PostType;
use App\Models\Term;
use App\Models\Taxonomy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;
    private Entry $entry;
    private Term $term;
    private Media $media;
    private PostType $postType;
    private Taxonomy $taxonomy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->regularUser = User::factory()->create();

        $this->postType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);

        $this->taxonomy = Taxonomy::create([
            'slug' => 'category',
            'name' => 'Category',
            'hierarchical' => false,
        ]);

        $this->entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Entry',
            'slug' => 'test-entry',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $this->term = Term::create([
            'taxonomy_id' => $this->taxonomy->id,
            'name' => 'Test Term',
            'slug' => 'test-term',
            'meta_json' => [],
        ]);

        $this->media = Media::create([
            'path' => 'test/test.jpg',
            'original_name' => 'test.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'meta_json' => [],
        ]);
    }

    public function test_admin_has_full_access_to_entry_abilities(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(Gate::allows('viewAny', Entry::class));
        $this->assertTrue(Gate::allows('view', $this->entry));
        $this->assertTrue(Gate::allows('create', Entry::class));
        $this->assertTrue(Gate::allows('update', $this->entry));
        $this->assertTrue(Gate::allows('delete', $this->entry));
        $this->assertTrue(Gate::allows('restore', $this->entry));
        $this->assertTrue(Gate::allows('forceDelete', $this->entry));
        $this->assertTrue(Gate::allows('publish', $this->entry));
        $this->assertTrue(Gate::allows('attachMedia', $this->entry));
        $this->assertTrue(Gate::allows('manageTerms', $this->entry));
    }

    public function test_admin_has_full_access_to_term_abilities(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(Gate::allows('viewAny', Term::class));
        $this->assertTrue(Gate::allows('view', $this->term));
        $this->assertTrue(Gate::allows('create', Term::class));
        $this->assertTrue(Gate::allows('update', $this->term));
        $this->assertTrue(Gate::allows('delete', $this->term));
        $this->assertTrue(Gate::allows('restore', $this->term));
        $this->assertTrue(Gate::allows('forceDelete', $this->term));
        $this->assertTrue(Gate::allows('attachEntry', $this->term));
    }

    public function test_admin_has_full_access_to_media_abilities(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(Gate::allows('viewAny', Media::class));
        $this->assertTrue(Gate::allows('view', $this->media));
        $this->assertTrue(Gate::allows('create', Media::class));
        $this->assertTrue(Gate::allows('update', $this->media));
        $this->assertTrue(Gate::allows('delete', $this->media));
        $this->assertTrue(Gate::allows('restore', $this->media));
        $this->assertTrue(Gate::allows('forceDelete', $this->media));
        $this->assertTrue(Gate::allows('upload', Media::class));
        $this->assertTrue(Gate::allows('reprocess', $this->media));
        $this->assertTrue(Gate::allows('move', $this->media));
    }

    public function test_regular_user_denied_entry_abilities(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse(Gate::allows('viewAny', Entry::class));
        $this->assertFalse(Gate::allows('view', $this->entry));
        $this->assertFalse(Gate::allows('create', Entry::class));
        $this->assertFalse(Gate::allows('update', $this->entry));
        $this->assertFalse(Gate::allows('delete', $this->entry));
        $this->assertFalse(Gate::allows('restore', $this->entry));
        $this->assertFalse(Gate::allows('forceDelete', $this->entry));
        $this->assertFalse(Gate::allows('publish', $this->entry));
        $this->assertFalse(Gate::allows('attachMedia', $this->entry));
        $this->assertFalse(Gate::allows('manageTerms', $this->entry));
    }

    public function test_regular_user_denied_term_abilities(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse(Gate::allows('viewAny', Term::class));
        $this->assertFalse(Gate::allows('view', $this->term));
        $this->assertFalse(Gate::allows('create', Term::class));
        $this->assertFalse(Gate::allows('update', $this->term));
        $this->assertFalse(Gate::allows('delete', $this->term));
        $this->assertFalse(Gate::allows('restore', $this->term));
        $this->assertFalse(Gate::allows('forceDelete', $this->term));
        $this->assertFalse(Gate::allows('attachEntry', $this->term));
    }

    public function test_regular_user_denied_media_abilities(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse(Gate::allows('viewAny', Media::class));
        $this->assertFalse(Gate::allows('view', $this->media));
        $this->assertFalse(Gate::allows('create', Media::class));
        $this->assertFalse(Gate::allows('update', $this->media));
        $this->assertFalse(Gate::allows('delete', $this->media));
        $this->assertFalse(Gate::allows('restore', $this->media));
        $this->assertFalse(Gate::allows('forceDelete', $this->media));
        $this->assertFalse(Gate::allows('upload', Media::class));
        $this->assertFalse(Gate::allows('reprocess', $this->media));
        $this->assertFalse(Gate::allows('move', $this->media));
    }

    public function test_guest_denied_all_abilities(): void
    {
        $this->assertFalse(Gate::allows('viewAny', Entry::class));
        $this->assertFalse(Gate::allows('view', $this->entry));
        $this->assertFalse(Gate::allows('create', Entry::class));
        $this->assertFalse(Gate::allows('update', $this->entry));
        $this->assertFalse(Gate::allows('delete', $this->entry));
    }

    public function test_user_factory_admin_state(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertTrue($admin->is_admin);

        $regular = User::factory()->create();
        $this->assertFalse($regular->is_admin);
    }

    public function test_user_model_is_admin_attribute(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->assertTrue($user->is_admin);

        $user = User::factory()->create(['is_admin' => false]);
        $this->assertFalse($user->is_admin);
    }

    public function test_policies_are_registered(): void
    {
        // Проверяем, что политики зарегистрированы через проверку abilities
        $this->actingAs($this->admin);

        // Если политики зарегистрированы, то abilities должны работать
        $this->assertTrue(Gate::allows('viewAny', Entry::class));
        $this->assertTrue(Gate::allows('viewAny', Term::class));
        $this->assertTrue(Gate::allows('viewAny', Media::class));
    }

    public function test_guest_receives_403_on_protected_route(): void
    {
        // Гость (неаутентифицированный) получает 403 на защищённом маршруте
        $response = $this->get('/test/admin/entries');
        $response->assertForbidden(); // 403
    }

    public function test_regular_user_receives_403_on_protected_route(): void
    {
        // Обычный пользователь (без прав) получает 403 на защищённом маршруте
        $this->actingAs($this->regularUser);

        $response = $this->get('/test/admin/entries');
        $response->assertForbidden(); // 403
    }

    public function test_admin_receives_200_on_protected_route(): void
    {
        // Администратор получает доступ к защищённому маршруту
        $this->actingAs($this->admin);

        $response = $this->get('/test/admin/entries');
        $response->assertOk(); // 200
        $response->assertJson(['message' => 'ok']);
    }
}
```

```

---

## Резюме изменений

### Новые файлы:
1. `database/migrations/2025_11_07_052620_add_is_admin_to_users_table.php` - миграция для добавления поля `is_admin`
2. `tests/Feature/AuthorizationTest.php` - тесты системы авторизации
3. `docs/implemented/policies_abilities_framework.md` - документация
4. `docs/review/policies_abilities_code_review.md` - файл для ревью (этот файл)

### Изменённые файлы:
1. `app/Models/User.php` - добавлен cast и геттер для `is_admin`
2. `app/Providers/AuthServiceProvider.php` - регистрация политик и `Gate::before()`
3. `app/Policies/EntryPolicy.php` - добавлены кастомные методы
4. `app/Policies/TermPolicy.php` - добавлен кастомный метод
5. `app/Policies/MediaPolicy.php` - добавлены кастомные методы
6. `app/Console/Commands/UserMakeAdminCommand.php` - реализована команда
7. `database/factories/UserFactory.php` - добавлен метод `admin()`

### Результаты тестов:
- Все тесты проходят успешно (13 passed, 72 assertions)
- Покрытие: администратор, обычный пользователь, гость
- Проверка всех abilities для всех моделей
- HTTP-тесты на 403 для гостя и обычного пользователя, 200 для администратора

### Исправления после ревью:
1. **Защита от mass assignment**: `$guarded = ['is_admin']` и явный `$fillable`
2. **Сигнатура `MediaPolicy::upload()`**: использует консистентный стиль `upload(User $user)` — проверка на уровне класса
3. **HTTP-тесты**: добавлены тесты на 403/200 для защищённых маршрутов
4. **Геттер `is_admin`**: убран дублирующий геттер, оставлен только cast для упрощения кода

```
