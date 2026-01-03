<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Path;
use App\Models\PathMediaConstraint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PathMediaConstraint>
 */
class PathMediaConstraintFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PathMediaConstraint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path_id' => Path::factory(),
            'allowed_mime' => $this->faker->randomElement([
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'video/mp4',
                'application/pdf',
            ]),
        ];
    }
}

