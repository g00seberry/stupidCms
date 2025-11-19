<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blueprint;
use App\Models\PostType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Blueprint>
 */
class BlueprintFactory extends Factory
{
    protected $model = Blueprint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_type_id' => PostType::factory(),
            'slug' => $this->faker->unique()->slug,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence,
            'type' => 'full',
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the blueprint is a component.
     */
    public function component(): self
    {
        return $this->state(fn (array $attributes) => [
            'post_type_id' => null,
            'type' => 'component',
        ]);
    }

    /**
     * Indicate that the blueprint is default.
     */
    public function default(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}

