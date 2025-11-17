<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media;

use App\Domain\Media\Services\StorageResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Тесты для StorageResolver.
 *
 * Проверяет резолвинг дисков для медиа-хранилища по коллекции и типу медиа.
 */
final class StorageResolverTest extends TestCase
{
    private StorageResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new StorageResolver();
    }

    public function test_resolves_disk_by_collection(): void
    {
        config()->set('media.disks', [
            'default' => 'media_default',
            'collections' => [
                'videos' => 'media_videos',
                'documents' => 'media_documents',
            ],
            'kinds' => [],
        ]);

        $this->assertSame('media_videos', $this->resolver->resolveDiskName('videos', null));
        $this->assertSame('media_documents', $this->resolver->resolveDiskName('documents', null));
    }

    public function test_resolves_disk_by_kind(): void
    {
        config()->set('media.disks', [
            'default' => 'media_default',
            'collections' => [],
            'kinds' => [
                'image' => 'media_images',
                'video' => 'media_videos',
                'audio' => 'media_audio',
                'document' => 'media_documents',
            ],
        ]);

        $this->assertSame('media_images', $this->resolver->resolveDiskName(null, 'image/jpeg'));
        $this->assertSame('media_videos', $this->resolver->resolveDiskName(null, 'video/mp4'));
        $this->assertSame('media_audio', $this->resolver->resolveDiskName(null, 'audio/mpeg'));
        $this->assertSame('media_documents', $this->resolver->resolveDiskName(null, 'application/pdf'));
    }

    public function test_resolves_default_disk_when_no_match(): void
    {
        config()->set('media.disks', [
            'default' => 'media_default',
            'collections' => [],
            'kinds' => [],
        ]);

        $this->assertSame('media_default', $this->resolver->resolveDiskName(null, null));
        $this->assertSame('media_default', $this->resolver->resolveDiskName('unknown', 'unknown/mime'));
    }

    public function test_resolves_media_disk_as_fallback(): void
    {
        config()->set('media.disks', []);

        $this->assertSame('media', $this->resolver->resolveDiskName(null, null));
    }

    public function test_detects_kind_from_mime(): void
    {
        config()->set('media.disks', [
            'default' => 'media_default',
            'collections' => [],
            'kinds' => [
                'image' => 'media_images',
                'video' => 'media_videos',
                'audio' => 'media_audio',
                'document' => 'media_documents',
            ],
        ]);

        // Проверяем определение kind через resolveDiskName
        $this->assertSame('media_images', $this->resolver->resolveDiskName(null, 'image/png'));
        $this->assertSame('media_images', $this->resolver->resolveDiskName(null, 'image/webp'));
        $this->assertSame('media_videos', $this->resolver->resolveDiskName(null, 'video/webm'));
        $this->assertSame('media_audio', $this->resolver->resolveDiskName(null, 'audio/aiff'));
        $this->assertSame('media_documents', $this->resolver->resolveDiskName(null, 'text/plain'));
    }

    public function test_handles_null_mime_gracefully(): void
    {
        config()->set('media.disks', [
            'default' => 'media_default',
            'collections' => [],
            'kinds' => [
                'image' => 'media_images',
            ],
        ]);

        $this->assertSame('media_default', $this->resolver->resolveDiskName(null, null));
    }

    public function test_returns_filesystem_for_upload(): void
    {
        Storage::fake('test_disk');
        config()->set('media.disks', [
            'default' => 'test_disk',
            'collections' => [],
            'kinds' => [],
        ]);

        $filesystem = $this->resolver->filesystemForUpload(null, null);

        $this->assertInstanceOf(Filesystem::class, $filesystem);
        // Проверяем, что можем использовать filesystem
        $filesystem->put('test.txt', 'test content');
        $this->assertTrue($filesystem->exists('test.txt'));
        $filesystem->delete('test.txt');
    }
}

