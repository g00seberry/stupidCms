<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use App\Models\MediaImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Фабрика для создания записей MediaImage.
 *
 * @extends Factory<MediaImage>
 */
class MediaImageFactory extends Factory
{
    /**
     * Имя модели, связанной с фабрикой.
     *
     * @var string
     */
    protected $model = MediaImage::class;

    /**
     * Определить значения атрибутов по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_id' => Media::factory(),
            'width' => $this->faker->numberBetween(100, 4000),
            'height' => $this->faker->numberBetween(100, 4000),
            'exif_json' => null,
        ];
    }

    /**
     * Указать медиа-файл для изображения.
     *
     * @param \App\Models\Media $media Медиа-файл
     * @return static
     */
    public function forMedia(Media $media): static
    {
        return $this->state(fn () => [
            'media_id' => $media->id,
        ]);
    }

    /**
     * Указать размеры изображения.
     *
     * @param int $width Ширина
     * @param int $height Высота
     * @return static
     */
    public function withDimensions(int $width, int $height): static
    {
        return $this->state(fn () => [
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Указать EXIF метаданные.
     *
     * @param array<string, mixed> $exif EXIF данные
     * @return static
     */
    public function withExif(array $exif): static
    {
        return $this->state(fn () => [
            'exif_json' => $exif,
        ]);
    }
}

