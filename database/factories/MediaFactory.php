<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Фабрика для создания записей Media.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Имя модели, связанной с фабрикой.
     *
     * @var string
     */
    protected $model = Media::class;

    /**
     * Определить значения атрибутов по умолчанию.
     *
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
            'checksum_sha256' => hash('sha256', $basename),
            'title' => $this->faker->sentence(3),
            'alt' => $this->faker->sentence(4),
            'collection' => $this->faker->randomElement(['banners', 'gallery', 'documents']),
        ];
    }

    /**
     * Указать, что медиа является изображением.
     *
     * @return static
     */
    public function image(): static
    {
        return $this->state(fn () => [
            'mime' => 'image/jpeg',
            'ext' => 'jpg',
        ]);
    }

    /**
     * Указать, что медиа является видео.
     *
     * @return static
     */
    public function video(): static
    {
        return $this->state(fn () => [
            'mime' => 'video/mp4',
            'ext' => 'mp4',
        ]);
    }

    /**
     * Указать, что медиа является аудио.
     *
     * @return static
     */
    public function audio(): static
    {
        return $this->state(fn () => [
            'mime' => 'audio/mpeg',
            'ext' => 'mp3',
        ]);
    }

    /**
     * Указать, что медиа является документом.
     *
     * @return static
     */
    public function document(): static
    {
        return $this->state(fn () => [
            'mime' => 'application/pdf',
            'ext' => 'pdf',
        ]);
    }

    /**
     * Создать медиа с связанной записью MediaImage.
     *
     * @param array<string, mixed> $imageAttributes Атрибуты для MediaImage
     * @return static
     */
    public function withImage(array $imageAttributes = []): static
    {
        return $this->afterCreating(function (Media $media) use ($imageAttributes) {
            \App\Models\MediaImage::factory()->for($media)->create($imageAttributes);
        });
    }
}


