<?php

namespace Database\Factories;

use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition(): array
    {
        $name = Str::headline($this->faker->unique()->words(2, true));

        return [
            'taxonomy_id' => Taxonomy::factory(),
            'name' => $name,
            'meta_json' => [],
        ];
    }

    public function forTaxonomy(Taxonomy $taxonomy): self
    {
        return $this->state(fn () => ['taxonomy_id' => $taxonomy->id]);
    }
}


