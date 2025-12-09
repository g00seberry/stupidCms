<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\{Entry, Media, Term, User};
use App\Policies\{EntryPolicy, MediaPolicy, TermPolicy};
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * Service Provider для авторизации и политик доступа.
 *
 * Регистрирует политики для моделей и определяет Gate abilities
 * для административных разрешений.
 *
 * @package App\Providers
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Маппинг политик для моделей.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Entry::class => EntryPolicy::class,
        Term::class  => TermPolicy::class,
        Media::class => MediaPolicy::class,
    ];

    /**
     * Зарегистрировать сервисы.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Загрузить сервисы авторизации.
     *
     * Регистрирует политики и определяет Gate abilities:
     * - Глобальный доступ для администратора (is_admin=true)
     * - manage.posttypes, manage.entries, manage.taxonomies, manage.terms
     * - media.* (read, create, update, delete, restore)
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Глобальный доступ для администратора
        Gate::before(function (User $user, string $ability) {
            // Полный доступ администратору
            return $user->is_admin ? true : null; // null => продолжить обычные проверки
        });

        Gate::define('manage.posttypes', static function (User $user): bool {
            return $user->hasAdminPermission('manage.posttypes');
        });

        Gate::define('manage.entries', static function (User $user): bool {
            return $user->hasAdminPermission('manage.entries');
        });

        Gate::define('manage.taxonomies', static function (User $user): bool {
            return $user->hasAdminPermission('manage.taxonomies');
        });

        Gate::define('manage.terms', static function (User $user): bool {
            return $user->hasAdminPermission('manage.terms');
        });

        Gate::define('media.read', static function (User $user): bool {
            return $user->hasAdminPermission('media.read');
        });

        Gate::define('media.create', static function (User $user): bool {
            return $user->hasAdminPermission('media.create');
        });

        Gate::define('media.update', static function (User $user): bool {
            return $user->hasAdminPermission('media.update');
        });

        Gate::define('media.delete', static function (User $user): bool {
            return $user->hasAdminPermission('media.delete');
        });

        Gate::define('media.restore', static function (User $user): bool {
            return $user->hasAdminPermission('media.restore');
        });


    }
}
