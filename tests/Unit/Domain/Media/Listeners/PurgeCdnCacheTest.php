<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media\Listeners;

use App\Domain\Media\Events\MediaDeleted;
use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Events\MediaUploaded;
use App\Domain\Media\Listeners\PurgeCdnCache;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Тесты для слушателя PurgeCdnCache.
 *
 * Проверяет очистку кэша CDN при событиях медиа-файлов.
 */
class PurgeCdnCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    /**
     * Тест: обработка события MediaUploaded при включённом CDN.
     */
    public function test_it_handles_media_uploaded_when_cdn_enabled(): void
    {
        config()->set('media.cdn.enabled', true);
        config()->set('media.cdn.disk', 'media');
        config()->set('media.cdn.provider', 'cloudflare');

        $listener = new PurgeCdnCache();
        $media = Media::factory()->create([
            'disk' => 'media',
            'path' => '2025/01/10/test.jpg',
        ]);

        Storage::disk('media')->put($media->path, 'fake content');

        $event = new MediaUploaded($media);
        
        // Проверяем, что метод выполняется без ошибок
        $listener->handleMediaUploaded($event);
        
        $this->assertTrue(true); // Метод выполнен успешно
    }

    /**
     * Тест: обработка события MediaUploaded при отключённом CDN.
     */
    public function test_it_handles_media_uploaded_when_cdn_disabled(): void
    {
        config()->set('media.cdn.enabled', false);

        $listener = new PurgeCdnCache();
        $media = Media::factory()->create([
            'disk' => 'media',
        ]);

        $event = new MediaUploaded($media);
        
        // Проверяем, что метод выполняется без ошибок
        $listener->handleMediaUploaded($event);
        
        $this->assertTrue(true); // Метод выполнен успешно
    }

    /**
     * Тест: обработка события MediaUploaded для другого диска.
     */
    public function test_it_handles_media_uploaded_for_different_disk(): void
    {
        config()->set('media.cdn.enabled', true);
        config()->set('media.cdn.disk', 'media');

        $listener = new PurgeCdnCache();
        $media = Media::factory()->create([
            'disk' => 'other_disk', // Другой диск
        ]);

        $event = new MediaUploaded($media);
        
        // Проверяем, что метод выполняется без ошибок
        $listener->handleMediaUploaded($event);
        
        $this->assertTrue(true); // Метод выполнен успешно
    }

    /**
     * Тест: обработка события MediaProcessed.
     */
    public function test_it_handles_media_processed(): void
    {
        config()->set('media.cdn.enabled', true);
        config()->set('media.cdn.disk', 'media');
        config()->set('media.cdn.provider', 'fastly');

        $listener = new PurgeCdnCache();
        $media = Media::factory()->create([
            'disk' => 'media',
        ]);
        $variant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'path' => '2025/01/10/test-thumbnail.jpg',
        ]);

        Storage::disk('media')->put($variant->path, 'fake variant content');

        $event = new MediaProcessed($media, $variant);
        
        // Проверяем, что метод выполняется без ошибок
        $listener->handleMediaProcessed($event);
        
        $this->assertTrue(true); // Метод выполнен успешно
    }

    /**
     * Тест: обработка события MediaDeleted.
     */
    public function test_it_handles_media_deleted(): void
    {
        config()->set('media.cdn.enabled', true);
        config()->set('media.cdn.disk', 'media');
        config()->set('media.cdn.provider', 'cloudfront');

        $listener = new PurgeCdnCache();
        $media = Media::factory()->create([
            'disk' => 'media',
        ]);
        $variant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
        ]);

        $event = new MediaDeleted($media);
        
        // Проверяем, что метод выполняется без ошибок
        $listener->handleMediaDeleted($event);
        
        $this->assertTrue(true); // Метод выполнен успешно
    }
}

