<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use App\Models\MediaMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaMetadata>
 */
class MediaMetadataFactory extends Factory
{
    protected $model = MediaMetadata::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_id' => Media::factory(),
            'duration_ms' => $this->faker->numberBetween(1000, 3600000),
            'bitrate_kbps' => $this->faker->numberBetween(128, 5000),
            'frame_rate' => $this->faker->randomFloat(2, 24.0, 60.0),
            'frame_count' => $this->faker->numberBetween(100, 100000),
            'video_codec' => $this->faker->randomElement(['h264', 'h265', 'vp9', 'av1']),
            'audio_codec' => $this->faker->randomElement(['aac', 'mp3', 'opus', 'vorbis']),
        ];
    }
}

