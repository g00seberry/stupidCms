<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ext = 'jpg';
        $basename = strtolower(Str::ulid()->toBase32());
        $path = now('UTC')->format('Y/m/d')."/{$basename}.{$ext}";

        return [
            'disk' => 'media',
            'path' => $path,
            'original_name' => "{$basename}.{$ext}",
            'ext' => $ext,
            'mime' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(10_000, 5_000_000),
            'width' => 1280,
            'height' => 720,
            'duration_ms' => null,
            'checksum_sha256' => hash('sha256', $basename),
            'exif_json' => null,
            'title' => $this->faker->sentence(3),
            'alt' => $this->faker->sentence(4),
            'collection' => $this->faker->randomElement(['banners', 'gallery', 'documents']),
        ];
    }

    public function image(): static
    {
        return $this->state(fn () => [
            'mime' => 'image/jpeg',
            'ext' => 'jpg',
            'width' => 1200,
            'height' => 900,
            'duration_ms' => null,
        ]);
    }

    public function document(): static
    {
        return $this->state(fn () => [
            'mime' => 'application/pdf',
            'ext' => 'pdf',
            'width' => null,
            'height' => null,
        ]);
    }
}


