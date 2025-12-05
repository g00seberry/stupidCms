<?php

namespace Database\Factories;

use App\Models\PostType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostType>
 */
class PostTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PostType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'template' => null,
            'options_json' => [],
        ];
    }

    /**
     * Indicate that the post type should have specific options.
     */
    public function withOptions(array $options): static
    {
        return $this->state(fn (array $attributes) => [
            'options_json' => $options,
        ]);
    }
}

