<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Media\MediaVariantStatus;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaVariant>
 */
class MediaVariantFactory extends Factory
{
    protected $model = MediaVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basename = strtolower(Str::ulid()->toBase32());
        $variant = $this->faker->randomElement(['thumbnail', 'medium', 'large']);
        $ext = 'jpg';
        $path = now('UTC')->format('Y/m/d')."/{$basename}-{$variant}.{$ext}";

        return [
            'media_id' => Media::factory(),
            'variant' => $variant,
            'path' => $path,
            'width' => $this->faker->numberBetween(100, 1920),
            'height' => $this->faker->numberBetween(100, 1080),
            'size_bytes' => $this->faker->numberBetween(1_000, 1_000_000),
            'status' => MediaVariantStatus::Ready,
            'error_message' => null,
            'started_at' => now('UTC'),
            'finished_at' => now('UTC'),
        ];
    }

    /**
     * Указать статус варианта.
     *
     * @param \App\Domain\Media\MediaVariantStatus $status Статус
     * @return static
     */
    public function withStatus(MediaVariantStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
        ]);
    }
}

