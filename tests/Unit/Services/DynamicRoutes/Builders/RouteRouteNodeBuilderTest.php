<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\Builders;

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\Builders\RouteRouteNodeBuilder;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Тест для RouteRouteNodeBuilder.
 *
 * Проверяет создание узлов типа ROUTE.
 */
class RouteRouteNodeBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
    }
    /**
     * Тест создания маршрута с CONTROLLER action_type.
     */
    public function test_build_route_with_controller_action(): void
    {
        $builder = new RouteRouteNodeBuilder();

        $data = [
            'kind' => RouteNodeKind::ROUTE,
            'uri' => '/test',
            'methods' => ['GET', 'POST'],
            'name' => 'test.route',
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\Http\Controllers\TestController@index',
        ];

        $node = $builder->build($data);

        $this->assertInstanceOf(RouteNode::class, $node);
        $this->assertEquals(RouteNodeKind::ROUTE, $node->kind);
        $this->assertEquals('/test', $node->uri);
        $this->assertEquals(['GET', 'POST'], $node->methods);
        $this->assertEquals('test.route', $node->name);
        $this->assertEquals(RouteNodeActionType::CONTROLLER, $node->action_type);
        $this->assertEquals('App\Http\Controllers\TestController@index', $node->action);
        $this->assertTrue($node->readonly);
        $this->assertLessThan(0, $node->id);
    }

    /**
     * Тест создания маршрута с ENTRY action_type.
     */
    public function test_build_route_with_entry_action(): void
    {
        $builder = new RouteRouteNodeBuilder();

        $data = [
            'kind' => RouteNodeKind::ROUTE,
            'uri' => '/page',
            'methods' => ['GET'],
            'action_type' => RouteNodeActionType::ENTRY,
            'entry_id' => 123,
        ];

        $node = $builder->build($data);

        $this->assertInstanceOf(RouteNode::class, $node);
        $this->assertEquals(RouteNodeActionType::ENTRY, $node->action_type);
        $this->assertEquals(123, $node->entry_id);
    }

    /**
     * Тест создания маршрута без action_type (по умолчанию CONTROLLER).
     */
    public function test_build_route_without_action_type(): void
    {
        $builder = new RouteRouteNodeBuilder();

        $data = [
            'kind' => RouteNodeKind::ROUTE,
            'uri' => '/test',
            'methods' => ['GET'],
        ];

        $node = $builder->build($data);

        $this->assertInstanceOf(RouteNode::class, $node);
        $this->assertEquals(RouteNodeActionType::CONTROLLER, $node->action_type);
    }

    /**
     * Тест supports для ROUTE.
     */
    public function test_supports_route(): void
    {
        $builder = new RouteRouteNodeBuilder();

        $this->assertTrue($builder->supports(RouteNodeKind::ROUTE));
        $this->assertFalse($builder->supports(RouteNodeKind::GROUP));
    }
}

