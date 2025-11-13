<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Slug\DefaultSlugifier;
use App\Support\Slug\DefaultUniqueSlugService;
use App\Support\Slug\Slugifier;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider для сервисов работы со slug'ами.
 *
 * Регистрирует Slugifier и UniqueSlugService как singleton.
 *
 * @package App\Providers
 */
class SlugServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать сервисы.
     *
     * Регистрирует Slugifier и UniqueSlugService как singleton.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Slugifier::class, function ($app) {
            return new DefaultSlugifier(config('stupidcms'));
        });

        $this->app->singleton(UniqueSlugService::class, DefaultUniqueSlugService::class);
    }

    /**
     * Загрузить сервисы.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}

