<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\Registrars;

use App\Enums\RouteNodeKind;
use App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use App\Services\DynamicRoutes\Registrars\RouteGroupRegistrar;
use App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarFactory;
use App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarInterface;
use App\Services\DynamicRoutes\Registrars\RouteRouteRegistrar;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Тест для RouteNodeRegistrarFactory.
 *
 * Проверяет создание и регистрацию регистраторов.
 */
class RouteNodeRegistrarFactoryTest extends TestCase
{
    private DynamicRouteGuard $guard;
    private ActionResolverFactory $actionResolverFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new DynamicRouteGuard();
        $this->actionResolverFactory = ActionResolverFactory::createDefault($this->guard);
        Log::shouldReceive('warning')->zeroOrMoreTimes();
    }

    /**
     * Тест создания фабрики с предустановленными регистраторами.
     */
    public function test_create_default_factory(): void
    {
        $factory = RouteNodeRegistrarFactory::createDefault($this->guard, $this->actionResolverFactory);

        $this->assertInstanceOf(RouteNodeRegistrarFactory::class, $factory);
    }

    /**
     * Тест получения регистратора для GROUP.
     */
    public function test_create_registrar_for_group(): void
    {
        $factory = RouteNodeRegistrarFactory::createDefault($this->guard, $this->actionResolverFactory);

        $registrar = $factory->create(RouteNodeKind::GROUP);

        $this->assertInstanceOf(RouteNodeRegistrarInterface::class, $registrar);
        $this->assertInstanceOf(RouteGroupRegistrar::class, $registrar);
    }

    /**
     * Тест получения регистратора для ROUTE.
     */
    public function test_create_registrar_for_route(): void
    {
        $factory = RouteNodeRegistrarFactory::createDefault($this->guard, $this->actionResolverFactory);

        $registrar = $factory->create(RouteNodeKind::ROUTE);

        $this->assertInstanceOf(RouteNodeRegistrarInterface::class, $registrar);
        $this->assertInstanceOf(RouteRouteRegistrar::class, $registrar);
    }

    /**
     * Тест регистрации кастомного регистратора.
     */
    public function test_register_custom_registrar(): void
    {
        $factory = new RouteNodeRegistrarFactory();
        $customRegistrar = new RouteGroupRegistrar($this->guard, null);

        $factory->register(RouteNodeKind::GROUP, $customRegistrar);

        $this->assertSame($customRegistrar, $factory->create(RouteNodeKind::GROUP));
    }

    /**
     * Тест create возвращает null для несуществующего регистратора.
     */
    public function test_create_returns_null_for_non_existent(): void
    {
        $factory = new RouteNodeRegistrarFactory();

        $result = $factory->create(RouteNodeKind::GROUP);

        $this->assertNull($result);
        
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('RouteNodeRegistrarFactory: регистратор не найден для типа узла', \Mockery::type('array'));
    }
}

