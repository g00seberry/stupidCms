<?php

namespace App\Providers;

use App\Support\EntrySlug\DefaultEntrySlugService;
use App\Support\EntrySlug\EntrySlugService;
use Illuminate\Support\ServiceProvider;

class EntrySlugServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(EntrySlugService::class, DefaultEntrySlugService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

