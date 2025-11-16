<?php

declare(strict_types=1);

namespace Tests\Unit\Media;

use App\Domain\Media\Images\ImageProcessor;
use App\Domain\Media\Images\ImageRef;
use App\Domain\Media\Services\OnDemandVariantService;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Фейковый ImageProcessor для теста вариантов.
 */
final class FakeImageProcessor implements ImageProcessor
{
    public function open(string $contents): ImageRef
    {
        return new ImageRef($contents);
    }

    public function width(ImageRef $image): int
    {
        return 4;
    }

    public function height(ImageRef $image): int
    {
        return 2;
    }

    public function resize(ImageRef $image, int $targetWidth, int $targetHeight): ImageRef
    {
        return new ImageRef($image->native . "#{$targetWidth}x{$targetHeight}");
    }

    public function encode(ImageRef $image, string $preferredExtension, int $quality = 82): array
    {
        return [
            'data' => "ENCODED({$preferredExtension},q={$quality})",
            'extension' => $preferredExtension,
            'mime' => match ($preferredExtension) {
                'png' => 'image/png',
                default => 'image/jpeg',
            },
        ];
    }

    public function destroy(ImageRef $image): void
    {
        // noop
    }

    public function supports(string $extension): bool
    {
        return true;
    }
}

final class OnDemandVariantServiceVariantConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_variant_format_and_quality_when_generating(): void
    {
        Storage::fake('media');
        Config::set('media.disk', 'media');
        Config::set('media.image.quality', 50); // глобальное качество
        Config::set('media.variants.thumbnail', ['max' => 320, 'format' => 'png', 'quality' => 77]);

        // Создаём минимальную запись Media
        /** @var Media $media */
        $media = Media::factory()->create([
            'disk' => 'media',
            'path' => 'orig/foo.jpg',
            'ext' => 'jpg',
            'mime' => 'image/jpeg',
            'width' => 10,
            'height' => 10,
        ]);

        // Оригинал на диске
        Storage::disk('media')->put('orig/foo.jpg', 'ORIGINAL_BYTES');

        // Подменяем процессор
        $service = new OnDemandVariantService(new FakeImageProcessor());

        $variant = $service->generateVariant($media, 'thumbnail');

        $this->assertInstanceOf(MediaVariant::class, $variant);
        $this->assertSame('thumbnail', $variant->variant);
        $this->assertSame(4, $variant->width);
        $this->assertSame(2, $variant->height);
        $this->assertStringEndsWith('-thumbnail.png', $variant->path);

        Storage::disk('media')->assertExists($variant->path);
        $saved = Storage::disk('media')->get($variant->path);
        $this->assertSame('ENCODED(png,q=77)', $saved);
    }
}


