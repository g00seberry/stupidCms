<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\Builders;

use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\Builders\RouteGroupNodeBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Тест для RouteGroupNodeBuilder.
 *
 * Проверяет создание узлов типа GROUP.
 */
class RouteGroupNodeBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
    }
    /**
     * Тест создания группы без дочерних узлов.
     */
    public function test_build_group_without_children(): void
    {
        $builder = new RouteGroupNodeBuilder();

        $data = [
            'kind' => RouteNodeKind::GROUP,
            'prefix' => 'api/v1',
            'middleware' => ['api'],
            'sort_order' => -999,
        ];

        $node = $builder->build($data);

        $this->assertInstanceOf(RouteNode::class, $node);
        $this->assertEquals(RouteNodeKind::GROUP, $node->kind);
        $this->assertEquals('api/v1', $node->prefix);
        $this->assertEquals(['api'], $node->middleware);
        $this->assertEquals(-999, $node->sort_order);
        $this->assertTrue($node->readonly);
        $this->assertTrue($node->enabled);
        $this->assertLessThan(0, $node->id); // Отрицательный ID
    }

    /**
     * Тест создания группы с дочерними узлами.
     */
    public function test_build_group_with_children(): void
    {
        $builder = new RouteGroupNodeBuilder();

        // Настраиваем callback для создания дочерних узлов
        $builder->setChildNodeBuilder(function (array $childData, ?RouteNode $parent, string $source): ?RouteNode {
            $childBuilder = new \App\Services\DynamicRoutes\Builders\RouteRouteNodeBuilder();
            return $childBuilder->build($childData, $parent, $source);
        });

        $data = [
            'kind' => RouteNodeKind::GROUP,
            'prefix' => 'api/v1',
            'children' => [
                [
                    'kind' => RouteNodeKind::ROUTE,
                    'uri' => '/test',
                    'methods' => ['GET'],
                ],
            ],
        ];

        $node = $builder->build($data);

        $this->assertInstanceOf(RouteNode::class, $node);
        $this->assertTrue($node->relationLoaded('children'));
        $this->assertInstanceOf(Collection::class, $node->children);
        $this->assertCount(1, $node->children);
        $this->assertEquals(RouteNodeKind::ROUTE, $node->children->first()->kind);
    }

    /**
     * Тест supports для GROUP.
     */
    public function test_supports_group(): void
    {
        $builder = new RouteGroupNodeBuilder();

        $this->assertTrue($builder->supports(RouteNodeKind::GROUP));
        $this->assertFalse($builder->supports(RouteNodeKind::ROUTE));
    }
}

