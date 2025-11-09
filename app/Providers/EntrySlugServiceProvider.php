<?php

namespace App\Providers;

use App\Domain\Entries\DefaultEntrySlugService;
use App\Domain\Entries\EntrySlugService;
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

