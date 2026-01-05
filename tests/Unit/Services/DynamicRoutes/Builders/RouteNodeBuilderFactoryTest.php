<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\Builders;

use App\Enums\RouteNodeKind;
use App\Services\DynamicRoutes\Builders\RouteGroupNodeBuilder;
use App\Services\DynamicRoutes\Builders\RouteNodeBuilderFactory;
use App\Services\DynamicRoutes\Builders\RouteNodeBuilderInterface;
use App\Services\DynamicRoutes\Builders\RouteRouteNodeBuilder;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Тест для RouteNodeBuilderFactory.
 *
 * Проверяет создание и регистрацию билдеров.
 */
class RouteNodeBuilderFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
    }
    /**
     * Тест создания фабрики с предустановленными билдерами.
     */
    public function test_create_default_factory(): void
    {
        $factory = RouteNodeBuilderFactory::createDefault();

        $this->assertInstanceOf(RouteNodeBuilderFactory::class, $factory);
        $this->assertTrue($factory->hasBuilder(RouteNodeKind::GROUP));
        $this->assertTrue($factory->hasBuilder(RouteNodeKind::ROUTE));
    }

    /**
     * Тест получения билдера для GROUP.
     */
    public function test_create_builder_for_group(): void
    {
        $factory = RouteNodeBuilderFactory::createDefault();

        $builder = $factory->create(RouteNodeKind::GROUP);

        $this->assertInstanceOf(RouteNodeBuilderInterface::class, $builder);
        $this->assertInstanceOf(RouteGroupNodeBuilder::class, $builder);
    }

    /**
     * Тест получения билдера для ROUTE.
     */
    public function test_create_builder_for_route(): void
    {
        $factory = RouteNodeBuilderFactory::createDefault();

        $builder = $factory->create(RouteNodeKind::ROUTE);

        $this->assertInstanceOf(RouteNodeBuilderInterface::class, $builder);
        $this->assertInstanceOf(RouteRouteNodeBuilder::class, $builder);
    }

    /**
     * Тест регистрации кастомного билдера.
     */
    public function test_register_custom_builder(): void
    {
        $factory = new RouteNodeBuilderFactory();
        $customBuilder = new RouteGroupNodeBuilder();

        $factory->register(RouteNodeKind::GROUP, $customBuilder);

        $this->assertTrue($factory->hasBuilder(RouteNodeKind::GROUP));
        $this->assertSame($customBuilder, $factory->create(RouteNodeKind::GROUP));
    }

    /**
     * Тест hasBuilder для несуществующего билдера.
     */
    public function test_has_builder_for_non_existent(): void
    {
        $factory = new RouteNodeBuilderFactory();

        // В пустой фабрике нет билдеров
        // Но мы не можем проверить несуществующий kind, так как enum ограничен
        // Проверим, что в пустой фабрике нет GROUP
        $this->assertFalse($factory->hasBuilder(RouteNodeKind::GROUP));
    }
}

