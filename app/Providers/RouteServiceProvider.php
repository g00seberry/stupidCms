<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\DeclarativeRouteLoader;
use App\Services\DynamicRoutes\DynamicRouteCache;
use App\Services\DynamicRoutes\Validators\DynamicRouteValidator;
use App\Services\DynamicRoutes\DynamicRouteRegistrar;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Service Provider для маршрутизации.
 *
 * Настраивает rate limiters для API, login, refresh.
 * Загружает маршруты в детерминированном порядке:
 * 1) Core → 2) Public API → 3) Admin API → 4) Content → 5) Dynamic Routes → 6) Fallback
 *
 * @package App\Providers
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * Путь к "home" маршруту приложения.
     *
     * Обычно пользователи перенаправляются сюда после аутентификации.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Определить привязки моделей, фильтры паттернов и другую конфигурацию маршрутов.
     *
     * Настраивает rate limiters и загружает маршруты в определённом порядке.
     *
     * @return void
     */
    public function boot(): void
    {
        // Настройка rate limiter для API (120 запросов в минуту)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter для login (10 попыток в минуту на связку email+IP)
        RateLimiter::for('login', function (Request $request) {
            $key = 'login:'.Str::lower($request->input('email')).'|'.$request->ip();
            return Limit::perMinute(10)->by($key);
        });

        // Rate limiter для refresh (20 попыток в минуту по хэшу cookie+IP)
        // Используем хэш cookie+IP для более точной идентификации клиента
        // Это помогает избежать ложных блокировок за NAT и ловит автоматы
        RateLimiter::for('refresh', function (Request $request) {
            $refreshToken = (string) $request->cookie(config('jwt.cookies.refresh'), '');
            // Fallback to sha256 if xxh128 is not available
            $algo = in_array('xxh128', hash_algos(), true) ? 'xxh128' : 'sha256';
            $key = hash($algo, $refreshToken . '|' . $request->ip());
            return Limit::perMinute(20)->by($key);
        });

        $this->routes(function () {
            // Порядок загрузки роутов (детерминированный):
            // 1) Core → 2) Public API → 3) Admin API → 4) Content → 5) Dynamic Routes → 6) Fallback
            
            // 1-5) Декларативные и динамические маршруты
            // Регистрируются через единый DynamicRouteRegistrar::register()
            // Декларативные и динамические маршруты объединены в общее дерево через RouteNodeRepository::getEnabledTree()
            // Порядок: декларативные (web_core.php → api.php → api_admin.php → web_content.php) → динамические из БД
            $this->registerAllRoutes();

            // 6) Fallback - строго последним
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
     * Зарегистрировать все маршруты (декларативные и динамические).
     *
     * Создаёт единый экземпляр DynamicRouteRegistrar с DeclarativeRouteLoader
     * и регистрирует все маршруты через метод register().
     * Порядок регистрации: декларативные → динамические из БД.
     * При ошибке регистрации логирует, но не прерывает загрузку приложения.
     *
     * @return void
     */
    private function registerAllRoutes(): void
    {
        try {
            $loader = new DeclarativeRouteLoader();
            $cache = app(DynamicRouteCache::class);
            $repository = new RouteNodeRepository($cache, $loader);
            $guard = new DynamicRouteValidator($repository, $loader);
            
            // Создаём фабрики для регистраторов и резолверов
            $actionResolverFactory = \App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory::createDefault($guard);
            $registrarFactory = \App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarFactory::createDefault($guard, $actionResolverFactory);
            
            $registrar = new DynamicRouteRegistrar($repository, $guard, $registrarFactory);

            // Регистрируем все маршруты (декларативные и динамические)
            // Декларативные и динамические маршруты объединены в общее дерево через RouteNodeRepository::getEnabledTree()
            // Порядок: декларативные маршруты идут первыми (имеют приоритет)
            // Если таблицы route_nodes нет, динамические маршруты просто не загрузятся
            $registrar->register();
        } catch (\Throwable $e) {
            // Логируем ошибку, но не прерываем загрузку приложения
            Log::error('Routes: ошибка при регистрации маршрутов', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

}

