<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlueprintEmbed>
 */
class BlueprintEmbedFactory extends Factory
{
    protected $model = BlueprintEmbed::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blueprint_id' => Blueprint::factory(),
            'embedded_blueprint_id' => Blueprint::factory(),
            'host_path_id' => null,
        ];
    }
}

