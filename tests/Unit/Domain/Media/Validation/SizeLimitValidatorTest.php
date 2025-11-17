<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media\Validation;

use App\Domain\Media\Validation\MediaValidationException;
use App\Domain\Media\Validation\SizeLimitValidator;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class SizeLimitValidatorTest extends TestCase
{
    public function test_validates_file_size_limit_exceeded(): void
    {
        $validator = new SizeLimitValidator([
            'max_size_bytes' => 1000,
        ]);

        $file = UploadedFile::fake()->create('test.jpg', 2000, 'image/jpeg');
        $actualSize = $file->getSize();

        $this->expectException(MediaValidationException::class);
        $this->expectExceptionMessage(sprintf('File size (%d bytes) exceeds maximum allowed size (1000 bytes)', $actualSize));

        $validator->validate($file, 'image/jpeg');
    }

    public function test_validates_image_width_limit_exceeded(): void
    {
        $validator = new SizeLimitValidator([
            'max_width' => 100,
        ]);

        $file = UploadedFile::fake()->image('test.jpg', 200, 50);

        $this->expectException(MediaValidationException::class);
        $this->expectExceptionMessage('Image width (200 px) exceeds maximum allowed width (100 px)');

        $validator->validate($file, 'image/jpeg');
    }

    public function test_validates_image_height_limit_exceeded(): void
    {
        $validator = new SizeLimitValidator([
            'max_height' => 100,
        ]);

        $file = UploadedFile::fake()->image('test.jpg', 50, 200);

        $this->expectException(MediaValidationException::class);
        $this->expectExceptionMessage('Image height (200 px) exceeds maximum allowed height (100 px)');

        $validator->validate($file, 'image/jpeg');
    }

    public function test_passes_validation_when_limits_not_set(): void
    {
        $validator = new SizeLimitValidator([]);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100)->size(1000);

        // Не должно быть исключения
        $validator->validate($file, 'image/jpeg');

        $this->assertTrue(true);
    }

    public function test_supports_all_mime_types(): void
    {
        $validator = new SizeLimitValidator([]);

        $this->assertTrue($validator->supports('image/jpeg'));
        $this->assertTrue($validator->supports('image/png'));
        $this->assertTrue($validator->supports('video/mp4'));
        $this->assertTrue($validator->supports('audio/mpeg'));
        $this->assertTrue($validator->supports('application/pdf'));
    }

    public function test_validates_dimensions_for_jpeg(): void
    {
        $validator = new SizeLimitValidator([
            'max_width' => 100,
            'max_height' => 100,
        ]);

        $jpegFile = UploadedFile::fake()->image('test.jpg', 150, 50);
        $this->expectException(MediaValidationException::class);
        $validator->validate($jpegFile, 'image/jpeg');
    }

    public function test_validates_dimensions_for_png(): void
    {
        $validator = new SizeLimitValidator([
            'max_width' => 100,
            'max_height' => 100,
        ]);

        $pngFile = UploadedFile::fake()->image('test.png', 50, 150);
        $this->expectException(MediaValidationException::class);
        $validator->validate($pngFile, 'image/png');
    }

    public function test_validates_dimensions_for_webp(): void
    {
        $validator = new SizeLimitValidator([
            'max_width' => 100,
            'max_height' => 100,
        ]);

        $webpFile = UploadedFile::fake()->image('test.webp', 150, 50);
        $this->expectException(MediaValidationException::class);
        $validator->validate($webpFile, 'image/webp');
    }

    public function test_validates_dimensions_for_gif(): void
    {
        $validator = new SizeLimitValidator([
            'max_width' => 100,
            'max_height' => 100,
        ]);

        $gifFile = UploadedFile::fake()->image('test.gif', 50, 150);
        $this->expectException(MediaValidationException::class);
        $validator->validate($gifFile, 'image/gif');
    }

    public function test_handles_corrupted_image_dimensions(): void
    {
        $validator = new SizeLimitValidator([
            'max_width' => 100,
            'max_height' => 100,
        ]);

        // Создаём файл, который не является валидным изображением
        $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');
        // Перезаписываем содержимое на невалидные данные
        file_put_contents($file->getRealPath(), 'invalid image data');

        // Не должно быть исключения, так как getimagesize вернёт false
        $validator->validate($file, 'image/jpeg');

        $this->assertTrue(true);
    }

    public function test_skips_dimension_validation_for_non_images(): void
    {
        $validator = new SizeLimitValidator([
            'max_width' => 100,
            'max_height' => 100,
        ]);

        // Видео файл
        $videoFile = UploadedFile::fake()->create('test.mp4', 1000, 'video/mp4');
        $validator->validate($videoFile, 'video/mp4');

        // Аудио файл
        $audioFile = UploadedFile::fake()->create('test.mp3', 1000, 'audio/mpeg');
        $validator->validate($audioFile, 'audio/mpeg');

        // PDF файл
        $pdfFile = UploadedFile::fake()->create('test.pdf', 1000, 'application/pdf');
        $validator->validate($pdfFile, 'application/pdf');

        $this->assertTrue(true);
    }
}

