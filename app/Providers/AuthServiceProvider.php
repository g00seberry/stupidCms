<?php

namespace App\Providers;

use App\Models\{Entry, Term, Media, ReservedRoute, User};
use App\Policies\{EntryPolicy, TermPolicy, MediaPolicy, RouteReservationPolicy};
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
    }
}
