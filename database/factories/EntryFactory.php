<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Entry>
 */
class EntryFactory extends Factory
{
    protected $model = Entry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_type_id' => PostType::factory(),
            'title' => fake()->sentence(),
            'slug' => fake()->unique()->slug(),
            'status' => 'draft',
            'published_at' => null,
            'author_id' => User::factory(),
            'data_json' => [],
            'seo_json' => null,
            'template_override' => null,
            'version' => 1,
        ];
    }

    /**
     * Indicate that the entry is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the entry is scheduled for future publication.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now()->addDay(),
        ]);
    }

    /**
     * Indicate that the entry is for a specific post type.
     */
    public function forPostType(PostType $postType): static
    {
        return $this->state(fn (array $attributes) => [
            'post_type_id' => $postType->id,
        ]);
    }

    /**
     * Indicate that the entry has specific author.
     */
    public function byAuthor(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'author_id' => $user->id,
        ]);
    }
}

