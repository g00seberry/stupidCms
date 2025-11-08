<?php

namespace App\Providers;

use App\Models\{Entry, Term, Media, Option, ReservedRoute, User};
use App\Policies\{EntryPolicy, TermPolicy, MediaPolicy, OptionPolicy, RouteReservationPolicy};
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
        Option::class => OptionPolicy::class,
        ReservedRoute::class => RouteReservationPolicy::class,
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

        Gate::define('options.read', static function (User $user): bool {
            return $user->hasAdminPermission('options.read');
        });

        Gate::define('options.write', static function (User $user): bool {
            return $user->hasAdminPermission('options.write');
        });

        Gate::define('options.delete', static function (User $user): bool {
            return $user->hasAdminPermission('options.delete');
        });

        Gate::define('options.restore', static function (User $user): bool {
            return $user->hasAdminPermission('options.restore');
        });
    }
}
