<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media;

use App\Domain\Media\Services\StorageResolver;
use Tests\TestCase;

final class StorageResolverTest extends TestCase
{
    public function test_it_returns_default_disk_when_no_collection_or_mime_is_provided(): void
    {
        config()->set('media.disks', [
            'default' => 'media_default',
            'collections' => [],
            'kinds' => [],
        ]);

        $resolver = new StorageResolver();

        self::assertSame('media_default', $resolver->resolveDiskName(null, null));
    }

    public function test_it_resolves_disk_by_collection_when_mapping_exists(): void
    {
        config()->set('media.disks', [
            'default' => 'media_default',
            'collections' => [
                'videos' => 'media_videos',
            ],
            'kinds' => [],
        ]);

        $resolver = new StorageResolver();

        self::assertSame('media_videos', $resolver->resolveDiskName('videos', null));
    }

    public function test_it_resolves_disk_by_kind_when_collection_is_not_mapped(): void
    {
        config()->set('media.disks', [
            'default' => 'media_default',
            'collections' => [],
            'kinds' => [
                'video' => 'media_videos',
            ],
        ]);

        $resolver = new StorageResolver();

        self::assertSame('media_videos', $resolver->resolveDiskName(null, 'video/mp4'));
    }

    public function test_it_falls_back_to_media_disk_when_no_disks_config_is_provided(): void
    {
        config()->set('media.disks', []);

        $resolver = new StorageResolver();

        self::assertSame('media', $resolver->resolveDiskName(null, null));
    }
}

