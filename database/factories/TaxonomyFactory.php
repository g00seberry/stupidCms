<?php

namespace Database\Factories;

use App\Models\Taxonomy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Taxonomy>
 */
class TaxonomyFactory extends Factory
{
    protected $model = Taxonomy::class;

    public function definition(): array
    {
        $label = Str::headline($this->faker->unique()->words(2, true));

        return [
            'slug' => $this->faker->unique()->slug(2),
            'label' => $label,
            'hierarchical' => $this->faker->boolean(),
            'options_json' => [],
        ];
    }

    public function hierarchical(bool $hierarchical = true): self
    {
        return $this->state(fn () => ['hierarchical' => $hierarchical]);
    }
}


