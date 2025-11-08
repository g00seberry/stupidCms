<?php

namespace Database\Factories;

use App\Models\Option;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Option>
 */
class OptionFactory extends Factory
{
    protected $model = Option::class;

    public function definition(): array
    {
        $namespace = $this->faker->randomElement(['site', 'integration', 'features']);
        $key = Str::of($this->faker->unique()->words(2, true))->snake();
        if ($key === '') {
            $key = 'option_' . Str::random(6);
        }

        return [
            'namespace' => $namespace,
            'key' => substr($key, 0, 64),
            'value_json' => [
                'value' => $this->faker->sentence(3),
            ],
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}

