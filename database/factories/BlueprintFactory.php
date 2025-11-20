<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blueprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blueprint>
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
        static $counter = 0;
        $counter++;
        $code = 'bp_' . $counter;

        return [
            'name' => 'Blueprint ' . $counter,
            'code' => $code,
            'description' => null,
        ];
    }
}

