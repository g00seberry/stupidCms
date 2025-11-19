<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Path;
use App\Models\Blueprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Path>
 */
class PathFactory extends Factory
{
    protected $model = Path::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word;

        return [
            'blueprint_id' => Blueprint::factory(),
            'name' => $name,
            'full_path' => $name,
            'data_type' => $this->faker->randomElement([
                'string', 'int', 'float', 'bool', 'text', 'json'
            ]),
            'cardinality' => 'one',
            'is_indexed' => true,
            'is_required' => false,
            'ref_target_type' => null,
            'validation_rules' => null,
            'ui_options' => null,
        ];
    }

    /**
     * Indicate that the path is a reference field.
     */
    public function ref(string $targetType = 'any'): self
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'ref',
            'ref_target_type' => $targetType,
        ]);
    }

    /**
     * Indicate that the path is a many-cardinality field.
     */
    public function many(): self
    {
        return $this->state(fn (array $attributes) => [
            'cardinality' => 'many',
        ]);
    }
}

