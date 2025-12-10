<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\Entry;
use App\Models\RouteNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Фабрика для модели RouteNode.
 *
 * @extends Factory<RouteNode>
 */
class RouteNodeFactory extends Factory
{
    protected $model = RouteNode::class;

    /**
     * Определить состояние модели по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'sort_order' => 0,
            'enabled' => true,
            'kind' => RouteNodeKind::ROUTE,
            'name' => null,
            'domain' => null,
            'prefix' => null,
            'namespace' => null,
            'methods' => ['GET'],
            'uri' => fake()->slug(),
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
            'entry_id' => null,
            'middleware' => null,
            'where' => null,
            'defaults' => null,
            'options' => null,
        ];
    }

    /**
     * Указать, что узел является группой.
     *
     * @return static
     */
    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => RouteNodeKind::GROUP,
            'methods' => null,
            'uri' => null,
            'action' => null,
            'prefix' => fake()->slug(),
        ]);
    }

    /**
     * Указать, что узел является маршрутом.
     *
     * @return static
     */
    public function route(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => RouteNodeKind::ROUTE,
            'methods' => ['GET'],
            'uri' => fake()->slug(),
            'action' => 'App\\Http\\Controllers\\TestController@show',
        ]);
    }

    /**
     * Указать родительский узел.
     *
     * @param \App\Models\RouteNode $parent Родительский узел
     * @return static
     */
    public function withParent(RouteNode $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Указать связанную Entry.
     *
     * @param \App\Models\Entry $entry Entry для связи
     * @return static
     */
    public function withEntry(Entry $entry): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => RouteNodeActionType::ENTRY,
            'entry_id' => $entry->id,
            'action' => null,
        ]);
    }

    /**
     * Указать, что узел включён.
     *
     * @return static
     */
    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => true,
        ]);
    }

    /**
     * Указать, что узел выключен.
     *
     * @return static
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
