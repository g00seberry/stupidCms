<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Entries\DefaultEntrySlugService;
use App\Domain\Entries\EntrySlugService;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider для EntrySlugService.
 *
 * Регистрирует EntrySlugService как singleton с реализацией DefaultEntrySlugService.
 *
 * @package App\Providers
 */
class EntrySlugServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать сервисы.
     *
     * Регистрирует EntrySlugService как singleton.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(EntrySlugService::class, DefaultEntrySlugService::class);
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

