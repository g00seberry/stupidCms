<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory;
use App\Services\DynamicRoutes\ActionResolvers\ControllerActionResolver;
use App\Services\DynamicRoutes\ActionResolvers\EntryActionResolver;
use App\Services\DynamicRoutes\ActionResolvers\RedirectActionResolver;
use App\Services\DynamicRoutes\ActionResolvers\ViewActionResolver;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Тест для ActionResolverFactory.
 *
 * Проверяет цепочку резолверов и порядок их проверки.
 */
class ActionResolverFactoryTest extends TestCase
{
    use RefreshDatabase;

    private DynamicRouteGuard $guard;
    private ActionResolverFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new DynamicRouteGuard();
        $this->factory = ActionResolverFactory::createDefault($this->guard);
        Log::shouldReceive('warning')->zeroOrMoreTimes();
    }

    /**
     * Тест создания фабрики с предустановленными резолверами.
     */
    public function test_create_default_factory(): void
    {
        $factory = ActionResolverFactory::createDefault($this->guard);

        $this->assertInstanceOf(ActionResolverFactory::class, $factory);
    }

    /**
     * Тест resolve для ENTRY использует EntryActionResolver.
     */
    public function test_resolve_entry_uses_entry_resolver(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::ENTRY,
        ]);

        $result = $this->factory->resolve($node);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('App\\Http\\Controllers\\EntryPageController', $result[0]);
        $this->assertEquals('show', $result[1]);
    }

    /**
     * Тест resolve для view: использует ViewActionResolver.
     */
    public function test_resolve_view_uses_view_resolver(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'view:pages.about',
        ]);

        $result = $this->factory->resolve($node);

        $this->assertIsCallable($result);
    }

    /**
     * Тест resolve для redirect: использует RedirectActionResolver.
     */
    public function test_resolve_redirect_uses_redirect_resolver(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'redirect:/new-page',
        ]);

        $result = $this->factory->resolve($node);

        $this->assertIsCallable($result);
    }

    /**
     * Тест resolve для Controller@method использует ControllerActionResolver.
     */
    public function test_resolve_controller_uses_controller_resolver(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
        ]);

        $result = $this->factory->resolve($node);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Тест порядок проверки резолверов: ENTRY проверяется первым.
     */
    public function test_resolver_order_entry_first(): void
    {
        // ENTRY должен обрабатываться первым, даже если есть другие резолверы
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::ENTRY,
        ]);

        $result = $this->factory->resolve($node);

        // Должен вернуть EntryPageController, а не что-то другое
        $this->assertIsArray($result);
        $this->assertEquals('App\\Http\\Controllers\\EntryPageController', $result[0]);
    }

    /**
     * Тест resolve возвращает fallback для CONTROLLER без action.
     *
     * ControllerActionResolver обрабатывает случай отсутствия action
     * и возвращает fallback (abort(404)).
     */
    public function test_resolve_returns_fallback_for_controller_without_action(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => null, // Нет action
        ]);

        $result = $this->factory->resolve($node);

        // ControllerActionResolver обрабатывает этот случай и возвращает fallback
        $this->assertIsCallable($result);
    }

    /**
     * Тест регистрации кастомного резолвера.
     */
    public function test_register_custom_resolver(): void
    {
        $factory = new ActionResolverFactory();
        $customResolver = new EntryActionResolver($this->guard);

        $factory->register($customResolver);

        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::ENTRY,
        ]);

        $result = $factory->resolve($node);

        $this->assertIsArray($result);
    }
}

