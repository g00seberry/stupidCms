<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Настройка rate limiter для API (60 запросов в минуту)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // Порядок загрузки роутов (детерминированный):
            // 1) Core → 2) Admin API → 3) Plugins → 4) Content → 5) Fallback
            
            // 1) System/Core routes - загружаются первыми
            // Включают: /, статические сервисные пути
            // Используют middleware('web') для веб-запросов с CSRF
            Route::middleware('web')
                ->group(base_path('routes/web_core.php'));

            // 2) Admin API routes - загружаются после core, но ДО плагинов
            // КРИТИЧНО: должны быть до плагинов, чтобы /api/v1/admin/* не перехватывались catch-all
            // Используют middleware('api') для stateless API без CSRF
            Route::middleware('api')
                ->prefix('api/v1/admin')
                ->group(base_path('routes/api_admin.php'));

            // 3) Plugin routes - загружаются третьими (детерминированный порядок)
            // В будущем будет сортировка по приоритету через PluginRegistry
            $this->mapPluginRoutes();

            // 4) Taxonomies & Content routes - загружаются четвёртыми
            // Включают: динамические контентные маршруты, таксономии
            // Catch-all маршруты должны быть здесь, а не в core
            Route::middleware('web')
                ->group(base_path('routes/web_content.php'));

            // 5) Fallback - строго последним
            // Обрабатывает все несовпавшие запросы (404) для ВСЕХ HTTP методов
            // ВАЖНО: Fallback НЕ должен быть под web middleware!
            // Иначе POST на несуществующий путь получит 419 CSRF вместо 404.
            // Контроллер сам определяет формат ответа (HTML/JSON) по типу запроса.
            // 
            // Регистрируем fallback для каждого метода отдельно, т.к. Route::fallback()
            // по умолчанию только для GET/HEAD
            $fallbackController = \App\Http\Controllers\FallbackController::class;
            Route::fallback($fallbackController); // GET, HEAD
            Route::match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '{any?}', $fallbackController)
                ->where('any', '.*')
                ->fallback();
        });
    }

    /**
     * Загружает маршруты плагинов в детерминированном порядке.
     * 
     * Плагины сортируются по приоритету (если указан) или по имени для стабильности.
     * Это гарантирует, что порядок загрузки роутов не меняется между запросами.
     * 
     * ВАЖНО: НЕ навешиваем middleware('web') сверху - пусть плагин сам решает,
     * какие middleware группы использовать (web|api). Иначе получится микс web+api,
     * что ломает семантику stateless API.
     */
    protected function mapPluginRoutes(): void
    {
        // Упрощённая версия: пока PluginRegistry не реализован, используем заглушку
        // В будущем здесь будет:
        // $plugins = app(\App\Domain\Plugins\PluginRegistry::class)->enabled();
        // $plugins = collect($plugins)->sortBy('priority')->values();
        // foreach ($plugins as $plugin) {
        //     require $plugin->routesFile();
        // }
        
        // Пока что просто проверяем наличие файла routes/plugins.php
        // Если он существует, загружаем его (плагин сам объявляет нужные группы)
        $pluginRoutesFile = base_path('routes/plugins.php');
        if (file_exists($pluginRoutesFile)) {
            require $pluginRoutesFile;
        }
    }
}

