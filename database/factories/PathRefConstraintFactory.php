<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Path;
use App\Models\PathRefConstraint;
use App\Models\PostType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PathRefConstraint>
 */
class PathRefConstraintFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PathRefConstraint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path_id' => Path::factory(),
            'allowed_post_type_id' => PostType::factory(),
        ];
    }
}
