<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\ActionResolvers\EntryActionResolver;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тест для EntryActionResolver.
 *
 * Проверяет резолвинг ENTRY действий.
 */
class EntryActionResolverTest extends TestCase
{
    use RefreshDatabase;

    private DynamicRouteGuard $guard;
    private EntryActionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new DynamicRouteGuard();
        $this->resolver = new EntryActionResolver($this->guard);
    }

    /**
     * Тест supports для ENTRY.
     */
    public function test_supports_entry(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::ENTRY,
        ]);

        $this->assertTrue($this->resolver->supports($node));
    }

    /**
     * Тест supports возвращает false для CONTROLLER.
     */
    public function test_supports_returns_false_for_controller(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
        ]);

        $this->assertFalse($this->resolver->supports($node));
    }

    /**
     * Тест resolve возвращает EntryPageController@show.
     */
    public function test_resolve_returns_entry_page_controller(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::ENTRY,
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('App\\Http\\Controllers\\EntryPageController', $result[0]);
        $this->assertEquals('show', $result[1]);
    }
}

