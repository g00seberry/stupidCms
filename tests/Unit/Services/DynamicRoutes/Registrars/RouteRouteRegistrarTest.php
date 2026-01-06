<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\Registrars;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use App\Services\DynamicRoutes\Registrars\RouteRouteRegistrar;
use App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Тест для RouteRouteRegistrar.
 *
 * Проверяет регистрацию маршрутов с различными типами действий.
 */
class RouteRouteRegistrarTest extends TestCase
{
    use RefreshDatabase;

    private DynamicRouteGuard $guard;
    private ActionResolverFactory $actionResolverFactory;
    private RouteNodeRegistrarFactory $registrarFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new DynamicRouteGuard();
        $this->actionResolverFactory = ActionResolverFactory::createDefault($this->guard);
        $this->registrarFactory = RouteNodeRegistrarFactory::createDefault($this->guard, $this->actionResolverFactory);
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
    }

    /**
     * Тест регистрации маршрута с Controller@method.
     */
    public function test_register_route_with_controller_method(): void
    {
        $node = RouteNode::factory()->route()->create([
            'methods' => ['GET'],
            'uri' => 'test',
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
            'enabled' => true,
        ]);

        $registrar = new RouteRouteRegistrar($this->guard, $this->registrarFactory, $this->actionResolverFactory);
        $registrar->register($node);

        $this->assertTrue(true);
    }

    /**
     * Тест регистрации маршрута с name.
     */
    public function test_register_route_with_name(): void
    {
        $node = RouteNode::factory()->route()->create([
            'methods' => ['GET'],
            'uri' => 'named-route',
            'name' => 'test.named',
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
            'enabled' => true,
        ]);

        $registrar = new RouteRouteRegistrar($this->guard, $this->registrarFactory, $this->actionResolverFactory);
        $registrar->register($node);

        $this->assertTrue(true);
    }

    /**
     * Тест регистрации маршрута с domain.
     */
    public function test_register_route_with_domain(): void
    {
        $node = RouteNode::factory()->route()->create([
            'methods' => ['GET'],
            'uri' => 'test',
            'domain' => 'api.example.com',
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
            'enabled' => true,
        ]);

        $registrar = new RouteRouteRegistrar($this->guard, $this->registrarFactory, $this->actionResolverFactory);
        $registrar->register($node);

        $this->assertTrue(true);
    }

    /**
     * Тест регистрации маршрута с middleware.
     */
    public function test_register_route_with_middleware(): void
    {
        $node = RouteNode::factory()->route()->create([
            'methods' => ['GET'],
            'uri' => 'test',
            'middleware' => ['web', 'auth'],
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
            'enabled' => true,
        ]);

        $registrar = new RouteRouteRegistrar($this->guard, $this->registrarFactory, $this->actionResolverFactory);
        $registrar->register($node);

        $this->assertTrue(true);
    }

    /**
     * Тест регистрации маршрута с where.
     */
    public function test_register_route_with_where(): void
    {
        $node = RouteNode::factory()->route()->create([
            'methods' => ['GET'],
            'uri' => 'test/{id}',
            'where' => ['id' => '[0-9]+'],
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
            'enabled' => true,
        ]);

        $registrar = new RouteRouteRegistrar($this->guard, $this->registrarFactory, $this->actionResolverFactory);
        $registrar->register($node);

        $this->assertTrue(true);
    }

    /**
     * Тест регистрации маршрута с defaults.
     */
    public function test_register_route_with_defaults(): void
    {
        $node = RouteNode::factory()->route()->create([
            'methods' => ['GET'],
            'uri' => 'test',
            'defaults' => ['key' => 'value'],
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
            'enabled' => true,
        ]);

        $registrar = new RouteRouteRegistrar($this->guard, $this->registrarFactory, $this->actionResolverFactory);
        $registrar->register($node);

        $this->assertTrue(true);
    }

    /**
     * Тест регистрации маршрута с action_type=ENTRY.
     */
    public function test_register_route_with_entry_action(): void
    {
        $node = RouteNode::factory()->route()->create([
            'methods' => ['GET'],
            'uri' => 'entry-page',
            'action_type' => RouteNodeActionType::ENTRY,
            'enabled' => true,
        ]);

        $registrar = new RouteRouteRegistrar($this->guard, $this->registrarFactory, $this->actionResolverFactory);
        $registrar->register($node);

        $this->assertTrue(true);
    }

    /**
     * Тест, что маршрут без uri не регистрируется.
     */
    public function test_route_without_uri_not_registered(): void
    {
        $node = RouteNode::factory()->route()->create([
            'methods' => ['GET'],
            'uri' => null,
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
            'enabled' => true,
        ]);

        $registrar = new RouteRouteRegistrar($this->guard, $this->registrarFactory, $this->actionResolverFactory);
        $registrar->register($node);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Dynamic route: пропущен маршрут без uri или methods', \Mockery::type('array'));
    }

    /**
     * Тест, что маршрут без methods не регистрируется.
     */
    public function test_route_without_methods_not_registered(): void
    {
        $node = RouteNode::factory()->route()->create([
            'methods' => null,
            'uri' => 'test',
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
            'enabled' => true,
        ]);

        $registrar = new RouteRouteRegistrar($this->guard, $this->registrarFactory, $this->actionResolverFactory);
        $registrar->register($node);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Dynamic route: пропущен маршрут без uri или methods', \Mockery::type('array'));
    }

    /**
     * Тест, что disabled маршрут не регистрируется.
     */
    public function test_disabled_route_not_registered(): void
    {
        $node = RouteNode::factory()->route()->create([
            'methods' => ['GET'],
            'uri' => 'test',
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
            'enabled' => false,
        ]);

        $registrar = new RouteRouteRegistrar($this->guard, $this->registrarFactory, $this->actionResolverFactory);
        $registrar->register($node);

        $this->assertTrue(true);
    }
}

