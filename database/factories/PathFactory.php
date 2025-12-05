<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Path>
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
        return [
            'blueprint_id' => Blueprint::factory(),
            'parent_id' => null,
            'name' => $this->faker->word(),
            'full_path' => fn (array $attributes) => $attributes['name'],
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => false,
            'is_readonly' => false,
            'sort_order' => 0,
            'validation_rules' => ['required' => false],
        ];
    }
}

