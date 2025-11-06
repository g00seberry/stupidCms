<?php

namespace App\Providers;

use App\Support\Slug\DefaultSlugifier;
use App\Support\Slug\DefaultUniqueSlugService;
use App\Support\Slug\Slugifier;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Support\ServiceProvider;

class SlugServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Slugifier::class, function ($app) {
            return new DefaultSlugifier(config('stupidcms'));
        });

        $this->app->singleton(UniqueSlugService::class, DefaultUniqueSlugService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

