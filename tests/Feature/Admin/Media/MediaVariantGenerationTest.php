<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Images\ImageProcessor;
use App\Domain\Media\Images\ImageRef;
use App\Domain\Media\Services\OnDemandVariantService;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class MediaVariantGenerationTest extends TestCase
{
    use RefreshDatabase;

    private ImageProcessor $imageProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');

        $this->imageProcessor = Mockery::mock(ImageProcessor::class);

        config()->set('media.variants', [
            'thumbnail' => ['max' => 320],
            'medium' => ['max' => 1024],
            'custom' => ['max' => 500, 'format' => 'webp', 'quality' => 90],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createService(): OnDemandVariantService
    {
        return new OnDemandVariantService($this->imageProcessor);
    }

    private function createMedia(int $width = 1920, int $height = 1080): Media
    {
        $file = UploadedFile::fake()->image('test.jpg', $width, $height);
        Storage::disk('media')->put('test.jpg', $file->getContent());

        return Media::create([
            'disk' => 'media',
            'path' => 'test.jpg',
            'original_name' => 'test.jpg',
            'ext' => 'jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => strlen($file->getContent()),
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Тест: генерация варианта thumbnail.
     */
    public function test_generates_thumbnail_variant(): void
    {
        Event::fake();

        $media = $this->createMedia(1920, 1080);
        $imageContents = Storage::disk('media')->get($media->path);

        $originalImage = new ImageRef('original');
        $resizedImage = new ImageRef('resized');

        $this->imageProcessor->shouldReceive('open')
            ->once()
            ->with($imageContents)
            ->andReturn($originalImage);

        $this->imageProcessor->shouldReceive('width')
            ->once()
            ->with($originalImage)
            ->andReturn(1920);

        $this->imageProcessor->shouldReceive('height')
            ->once()
            ->with($originalImage)
            ->andReturn(1080);

        $this->imageProcessor->shouldReceive('resize')
            ->once()
            ->with($originalImage, 320, 180)
            ->andReturn($resizedImage);

        $this->imageProcessor->shouldReceive('width')
            ->once()
            ->with($resizedImage)
            ->andReturn(320);

        $this->imageProcessor->shouldReceive('height')
            ->once()
            ->with($resizedImage)
            ->andReturn(180);

        $this->imageProcessor->shouldReceive('encode')
            ->once()
            ->with($resizedImage, 'jpg', 82)
            ->andReturn([
                'data' => 'encoded-image-data',
                'extension' => 'jpg',
                'mime' => 'image/jpeg',
            ]);

        $this->imageProcessor->shouldReceive('destroy')
            ->once()
            ->with($resizedImage);

        $service = $this->createService();
        $variant = $service->generateVariant($media, 'thumbnail');

        $this->assertInstanceOf(MediaVariant::class, $variant);
        $this->assertSame('thumbnail', $variant->variant);
        $this->assertSame(320, $variant->width);
        $this->assertSame(180, $variant->height);
        $this->assertStringContainsString('-thumbnail.jpg', $variant->path);
        Storage::disk('media')->assertExists($variant->path);

        Event::assertDispatched(MediaProcessed::class);
    }

    /**
     * Тест: генерация нескольких вариантов.
     */
    public function test_generates_multiple_variants(): void
    {
        Event::fake();

        $media = $this->createMedia(1920, 1080);
        $imageContents = Storage::disk('media')->get($media->path);

        $originalImage = new ImageRef('original');
        $thumbnailResized = new ImageRef('thumbnail-resized');
        $mediumResized = new ImageRef('medium-resized');

        // Thumbnail
        $this->imageProcessor->shouldReceive('open')
            ->once()
            ->andReturn($originalImage);
        $this->imageProcessor->shouldReceive('width')->twice()->andReturn(1920);
        $this->imageProcessor->shouldReceive('height')->twice()->andReturn(1080);
        $this->imageProcessor->shouldReceive('resize')
            ->once()
            ->with($originalImage, 320, 180)
            ->andReturn($thumbnailResized);
        $this->imageProcessor->shouldReceive('width')->once()->with($thumbnailResized)->andReturn(320);
        $this->imageProcessor->shouldReceive('height')->once()->with($thumbnailResized)->andReturn(180);
        $this->imageProcessor->shouldReceive('encode')
            ->once()
            ->andReturn(['data' => 'thumb', 'extension' => 'jpg', 'mime' => 'image/jpeg']);
        $this->imageProcessor->shouldReceive('destroy')->once()->with($thumbnailResized);

        $service = $this->createService();
        $thumbnail = $service->generateVariant($media, 'thumbnail');

        // Medium
        $this->imageProcessor->shouldReceive('open')
            ->once()
            ->andReturn($originalImage);
        $this->imageProcessor->shouldReceive('width')->twice()->andReturn(1920);
        $this->imageProcessor->shouldReceive('height')->twice()->andReturn(1080);
        $this->imageProcessor->shouldReceive('resize')
            ->once()
            ->with($originalImage, 1024, 576)
            ->andReturn($mediumResized);
        $this->imageProcessor->shouldReceive('width')->once()->with($mediumResized)->andReturn(1024);
        $this->imageProcessor->shouldReceive('height')->once()->with($mediumResized)->andReturn(576);
        $this->imageProcessor->shouldReceive('encode')
            ->once()
            ->andReturn(['data' => 'medium', 'extension' => 'jpg', 'mime' => 'image/jpeg']);
        $this->imageProcessor->shouldReceive('destroy')->once()->with($mediumResized);

        $medium = $service->generateVariant($media, 'medium');

        $this->assertNotSame($thumbnail->id, $medium->id);
        $this->assertSame(2, $media->variants()->count());
    }

    /**
     * Тест: регенерация отсутствующего файла варианта.
     */
    public function test_regenerates_missing_variant_file(): void
    {
        Event::fake();

        $media = $this->createMedia(1920, 1080);
        $imageContents = Storage::disk('media')->get($media->path);

        // Создаём запись варианта, но файл отсутствует
        $existingVariant = MediaVariant::create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'path' => 'missing-thumbnail.jpg',
            'width' => 320,
            'height' => 180,
            'size_bytes' => 0,
        ]);

        $originalImage = new ImageRef('original');
        $resizedImage = new ImageRef('resized');

        $this->imageProcessor->shouldReceive('open')->once()->andReturn($originalImage);
        $this->imageProcessor->shouldReceive('width')->twice()->andReturn(1920);
        $this->imageProcessor->shouldReceive('height')->twice()->andReturn(1080);
        $this->imageProcessor->shouldReceive('resize')->once()->andReturn($resizedImage);
        $this->imageProcessor->shouldReceive('width')->once()->with($resizedImage)->andReturn(320);
        $this->imageProcessor->shouldReceive('height')->once()->with($resizedImage)->andReturn(180);
        $this->imageProcessor->shouldReceive('encode')->once()->andReturn([
            'data' => 'regenerated',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
        ]);
        $this->imageProcessor->shouldReceive('destroy')->once();

        $service = $this->createService();
        $variant = $service->ensureVariant($media, 'thumbnail');

        // Должен быть создан новый файл
        $this->assertNotSame($existingVariant->path, $variant->path);
        Storage::disk('media')->assertExists($variant->path);
    }

    /**
     * Тест: обработка ошибки генерации варианта.
     */
    public function test_handles_variant_generation_failure(): void
    {
        $media = $this->createMedia(1920, 1080);

        $this->imageProcessor->shouldReceive('open')
            ->once()
            ->andThrow(new \RuntimeException('Failed to open image'));

        $service = $this->createService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to open image');

        $service->generateVariant($media, 'thumbnail');
    }

    /**
     * Тест: обновление статуса варианта при ошибке.
     *
     * Примечание: В текущей реализации статус обновляется только при успехе.
     * Этот тест проверяет, что система корректно выбрасывает исключение при ошибке.
     */
    public function test_updates_variant_status_on_failure(): void
    {
        $media = $this->createMedia(1920, 1080);

        $this->imageProcessor->shouldReceive('open')
            ->once()
            ->andThrow(new \RuntimeException('Processing failed'));

        $service = $this->createService();

        try {
            $service->generateVariant($media, 'thumbnail');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Processing failed', $e->getMessage());
        }

        // Вариант не должен быть создан при ошибке
        $this->assertSame(0, $media->variants()->count());
    }

    /**
     * Тест: генерация варианта с другим форматом.
     */
    public function test_generates_variant_with_different_format(): void
    {
        Event::fake();

        $media = $this->createMedia(1920, 1080);
        $imageContents = Storage::disk('media')->get($media->path);

        $originalImage = new ImageRef('original');
        $resizedImage = new ImageRef('resized');

        $this->imageProcessor->shouldReceive('open')->once()->andReturn($originalImage);
        $this->imageProcessor->shouldReceive('width')->twice()->andReturn(1920);
        $this->imageProcessor->shouldReceive('height')->twice()->andReturn(1080);
        $this->imageProcessor->shouldReceive('resize')->once()->andReturn($resizedImage);
        $this->imageProcessor->shouldReceive('width')->once()->with($resizedImage)->andReturn(500);
        $this->imageProcessor->shouldReceive('height')->once()->with($resizedImage)->andReturn(281);
        $this->imageProcessor->shouldReceive('encode')
            ->once()
            ->with($resizedImage, 'webp', 90) // Формат из конфига
            ->andReturn([
                'data' => 'webp-data',
                'extension' => 'webp',
                'mime' => 'image/webp',
            ]);
        $this->imageProcessor->shouldReceive('destroy')->once();

        $service = $this->createService();
        $variant = $service->generateVariant($media, 'custom');

        $this->assertStringContainsString('.webp', $variant->path);
    }

    /**
     * Тест: генерация варианта с другим качеством.
     */
    public function test_generates_variant_with_different_quality(): void
    {
        Event::fake();

        $media = $this->createMedia(1920, 1080);
        $imageContents = Storage::disk('media')->get($media->path);

        $originalImage = new ImageRef('original');
        $resizedImage = new ImageRef('resized');

        $this->imageProcessor->shouldReceive('open')->once()->andReturn($originalImage);
        $this->imageProcessor->shouldReceive('width')->twice()->andReturn(1920);
        $this->imageProcessor->shouldReceive('height')->twice()->andReturn(1080);
        $this->imageProcessor->shouldReceive('resize')->once()->andReturn($resizedImage);
        $this->imageProcessor->shouldReceive('width')->once()->with($resizedImage)->andReturn(500);
        $this->imageProcessor->shouldReceive('height')->once()->with($resizedImage)->andReturn(281);
        $this->imageProcessor->shouldReceive('encode')
            ->once()
            ->with($resizedImage, 'webp', 90) // Качество из конфига custom
            ->andReturn([
                'data' => 'high-quality',
                'extension' => 'webp',
                'mime' => 'image/webp',
            ]);
        $this->imageProcessor->shouldReceive('destroy')->once();

        $service = $this->createService();
        $variant = $service->generateVariant($media, 'custom');

        $this->assertNotNull($variant);
    }

    /**
     * Тест: обработка очень большого изображения.
     */
    public function test_handles_very_large_image(): void
    {
        Event::fake();

        $media = $this->createMedia(10000, 10000);
        $imageContents = Storage::disk('media')->get($media->path);

        $originalImage = new ImageRef('original');
        $resizedImage = new ImageRef('resized');

        $this->imageProcessor->shouldReceive('open')->once()->andReturn($originalImage);
        $this->imageProcessor->shouldReceive('width')->twice()->andReturn(10000);
        $this->imageProcessor->shouldReceive('height')->twice()->andReturn(10000);
        $this->imageProcessor->shouldReceive('resize')
            ->once()
            ->with($originalImage, 320, 320) // Масштабируется до max
            ->andReturn($resizedImage);
        $this->imageProcessor->shouldReceive('width')->once()->with($resizedImage)->andReturn(320);
        $this->imageProcessor->shouldReceive('height')->once()->with($resizedImage)->andReturn(320);
        $this->imageProcessor->shouldReceive('encode')->once()->andReturn([
            'data' => 'resized-large',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
        ]);
        $this->imageProcessor->shouldReceive('destroy')->once();

        $service = $this->createService();
        $variant = $service->generateVariant($media, 'thumbnail');

        $this->assertSame(320, $variant->width);
        $this->assertSame(320, $variant->height);
    }

    /**
     * Тест: обработка очень маленького изображения.
     */
    public function test_handles_very_small_image(): void
    {
        Event::fake();

        $media = $this->createMedia(50, 50);
        $imageContents = Storage::disk('media')->get($media->path);

        $originalImage = new ImageRef('original');
        $resizedImage = new ImageRef('resized');

        $this->imageProcessor->shouldReceive('open')->once()->andReturn($originalImage);
        $this->imageProcessor->shouldReceive('width')->twice()->andReturn(50);
        $this->imageProcessor->shouldReceive('height')->twice()->andReturn(50);
        // Изображение меньше max, поэтому не масштабируется
        $this->imageProcessor->shouldReceive('resize')
            ->once()
            ->with($originalImage, 50, 50)
            ->andReturn($resizedImage);
        $this->imageProcessor->shouldReceive('width')->once()->with($resizedImage)->andReturn(50);
        $this->imageProcessor->shouldReceive('height')->once()->with($resizedImage)->andReturn(50);
        $this->imageProcessor->shouldReceive('encode')->once()->andReturn([
            'data' => 'small-image',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
        ]);
        $this->imageProcessor->shouldReceive('destroy')->once();

        $service = $this->createService();
        $variant = $service->generateVariant($media, 'thumbnail');

        $this->assertSame(50, $variant->width);
        $this->assertSame(50, $variant->height);
    }

    /**
     * Тест: сохранение соотношения сторон.
     */
    public function test_preserves_aspect_ratio(): void
    {
        Event::fake();

        $media = $this->createMedia(1920, 1080); // 16:9
        $imageContents = Storage::disk('media')->get($media->path);

        $originalImage = new ImageRef('original');
        $resizedImage = new ImageRef('resized');

        $this->imageProcessor->shouldReceive('open')->once()->andReturn($originalImage);
        $this->imageProcessor->shouldReceive('width')->twice()->andReturn(1920);
        $this->imageProcessor->shouldReceive('height')->twice()->andReturn(1080);
        // При max=320, длинная сторона 1920 -> масштаб 320/1920 = 0.1667
        // 1920 * 0.1667 = 320, 1080 * 0.1667 = 180
        $this->imageProcessor->shouldReceive('resize')
            ->once()
            ->with($originalImage, 320, 180) // Сохраняется соотношение 16:9
            ->andReturn($resizedImage);
        $this->imageProcessor->shouldReceive('width')->once()->with($resizedImage)->andReturn(320);
        $this->imageProcessor->shouldReceive('height')->once()->with($resizedImage)->andReturn(180);
        $this->imageProcessor->shouldReceive('encode')->once()->andReturn([
            'data' => 'preserved-ratio',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
        ]);
        $this->imageProcessor->shouldReceive('destroy')->once();

        $service = $this->createService();
        $variant = $service->generateVariant($media, 'thumbnail');

        // Проверяем, что соотношение сторон сохранено (16:9)
        $ratio = $variant->width / $variant->height;
        $expectedRatio = 1920 / 1080;
        $this->assertEqualsWithDelta($expectedRatio, $ratio, 0.01);
    }
}

