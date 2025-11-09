<?php

declare(strict_types=1);

namespace Plugins\Example;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ExamplePluginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware(['api', 'jwt.auth'])
            ->prefix('api/v1')
            ->group(__DIR__ . '/../routes/plugin.php');
    }
}

