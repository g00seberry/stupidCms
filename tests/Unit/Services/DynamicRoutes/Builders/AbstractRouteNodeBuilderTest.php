<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\Builders;

use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\Builders\AbstractRouteNodeBuilder;
use App\Services\DynamicRoutes\Builders\RouteNodeBuilderInterface;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Тест для AbstractRouteNodeBuilder.
 *
 * Проверяет общую функциональность базового класса билдеров.
 */
class AbstractRouteNodeBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
    }
    /**
     * Тест нормализации kind из строки.
     */
    public function test_normalize_kind_from_string(): void
    {
        $builder = $this->createTestBuilder();
        
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('normalizeKind');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'group');
        $this->assertEquals(RouteNodeKind::GROUP, $result);

        $result = $method->invoke($builder, 'route');
        $this->assertEquals(RouteNodeKind::ROUTE, $result);
    }

    /**
     * Тест нормализации kind из enum.
     */
    public function test_normalize_kind_from_enum(): void
    {
        $builder = $this->createTestBuilder();
        
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('normalizeKind');
        $method->setAccessible(true);

        $result = $method->invoke($builder, RouteNodeKind::GROUP);
        $this->assertEquals(RouteNodeKind::GROUP, $result);
    }

    /**
     * Тест генерации отрицательных ID.
     */
    public function test_generate_declarative_id(): void
    {
        $builder1 = $this->createTestBuilder();
        $builder2 = $this->createTestBuilder();
        
        $reflection = new \ReflectionClass($builder1);
        $method = $reflection->getMethod('generateDeclarativeId');
        $method->setAccessible(true);

        $id1 = $method->invoke($builder1);
        $id2 = $method->invoke($builder2);

        $this->assertLessThan(0, $id1);
        $this->assertLessThan(0, $id2);
        $this->assertLessThan($id1, $id2); // ID должны уменьшаться
    }

    /**
     * Создать тестовый билдер для проверки protected методов.
     */
    private function createTestBuilder(): RouteNodeBuilderInterface
    {
        return new class extends AbstractRouteNodeBuilder {
            public function supports(\App\Enums\RouteNodeKind $kind): bool
            {
                return true;
            }

            protected function buildSpecificFields(\App\Models\RouteNode $node, array $data, string $source): void
            {
                // Пустая реализация для теста
            }
        };
    }
}

