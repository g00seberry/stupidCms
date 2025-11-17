<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media;

use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Services\OnDemandVariantService;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Тесты для OnDemandVariantService.
 *
 * Проверяет генерацию вариантов медиа-файлов с различными настройками.
 */
final class OnDemandVariantServiceTest extends TestCase
{
    use RefreshDatabase;

    private OnDemandVariantService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OnDemandVariantService::class);
    }

    public function test_ensure_variant_generates_synchronously_in_tests_even_if_queue_not_sync(): void
    {
        Storage::fake('media');
        config()->set('queue.default', 'database'); // не sync
        Queue::fake();

        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $variant = $this->service->ensureVariant($media, 'thumbnail');

        // В тестах generation выполняется синхронно, job не пушится
        Queue::assertNothingPushed();
        $this->assertSame('thumbnail', $variant->variant);
    }

    public function test_generates_variant_with_custom_format(): void
    {
        Storage::fake('media');
        config()->set('media.variants', [
            'webp_thumb' => [
                'max' => 320,
                'format' => 'webp',
            ],
        ]);

        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $variant = $this->service->generateVariant($media, 'webp_thumb');

        $this->assertSame('webp_thumb', $variant->variant);
        $this->assertTrue(Storage::disk('media')->exists($variant->path));
        $this->assertStringEndsWith('.webp', $variant->path);
    }

    public function test_generates_variant_with_custom_quality(): void
    {
        Storage::fake('media');
        config()->set('media.variants', [
            'low_quality' => [
                'max' => 320,
                'quality' => 50,
            ],
        ]);
        config()->set('media.image.quality', 82);

        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $variant = $this->service->generateVariant($media, 'low_quality');

        $this->assertSame('low_quality', $variant->variant);
        $this->assertTrue(Storage::disk('media')->exists($variant->path));
    }

    public function test_handles_original_smaller_than_max(): void
    {
        Storage::fake('media');
        config()->set('media.variants', [
            'large' => [
                'max' => 5000, // Больше оригинала
            ],
        ]);

        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $variant = $this->service->generateVariant($media, 'large');

        $this->assertSame('large', $variant->variant);
        // Размеры должны остаться оригинальными или близкими
        $this->assertNotNull($variant->width);
        $this->assertNotNull($variant->height);
    }

    public function test_handles_file_read_failure(): void
    {
        Storage::fake('media');

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => 'nonexistent/file.png',
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read original media');

        $this->service->generateVariant($media, 'thumbnail');
    }

    public function test_updates_existing_variant_record(): void
    {
        Storage::fake('media');
        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        // Создаём существующий вариант
        $existingVariant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'width' => 100,
            'height' => 100,
        ]);

        $variant = $this->service->generateVariant($media, 'thumbnail');

        $this->assertSame($existingVariant->id, $variant->id);
        $this->assertNotSame(100, $variant->width); // Размеры должны обновиться
    }

    public function test_dispatches_media_processed_event(): void
    {
        Storage::fake('media');
        Event::fake();

        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $variant = $this->service->generateVariant($media, 'thumbnail');

        Event::assertDispatched(MediaProcessed::class, function ($event) use ($media, $variant) {
            return $event->media->id === $media->id && $event->variant->id === $variant->id;
        });
    }

    public function test_builds_variant_path_correctly(): void
    {
        Storage::fake('media');
        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $variant = $this->service->generateVariant($media, 'thumbnail');

        $this->assertStringContainsString('krea-edit-thumbnail', $variant->path);
        $this->assertStringContainsString('2025/11/16', $variant->path);
    }

    public function test_handles_variant_without_extension(): void
    {
        Storage::fake('media');
        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $variant = $this->service->generateVariant($media, 'thumbnail');

        $this->assertSame('thumbnail', $variant->variant);
        $this->assertTrue(Storage::disk('media')->exists($variant->path));
    }

    public function test_handles_dot_directory_path(): void
    {
        Storage::fake('media');
        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = './krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $variant = $this->service->generateVariant($media, 'thumbnail');

        $this->assertSame('thumbnail', $variant->variant);
        $this->assertStringStartsWith('krea-edit-thumbnail', $variant->path);
        $this->assertStringNotContainsString('./', $variant->path);
    }
}


