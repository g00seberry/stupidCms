<?php

declare(strict_types=1);

use App\Domain\Media\Services\StorageResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('resolves storage disk by collection', function () {
    Config::set('media.disks.collections', [
        'videos' => 'media_videos',
        'documents' => 'media_documents',
    ]);

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName('videos', null))->toBe('media_videos')
        ->and($resolver->resolveDiskName('documents', null))->toBe('media_documents');
});

test('returns default disk for unknown collection', function () {
    Config::set('media.disks.default', 'media_default');
    Config::set('media.disks.collections', []);

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName('unknown', null))->toBe('media_default');
});

test('supports s3, local, public disks', function () {
    Config::set('media.disks.collections', [
        'videos' => 's3',
    ]);
    Config::set('media.disks.default', 'local');

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName('videos', null))->toBe('s3')
        ->and($resolver->resolveDiskName('unknown', null))->toBe('local');
});

test('resolves disk by kind when collection not found', function () {
    Config::set('media.disks.collections', []);
    Config::set('media.disks.kinds', [
        'image' => 'media_images',
        'video' => 'media_videos',
    ]);
    Config::set('media.disks.default', 'media');

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName(null, 'image/jpeg'))->toBe('media_images')
        ->and($resolver->resolveDiskName(null, 'video/mp4'))->toBe('media_videos');
});

test('detects kind from mime type', function () {
    Config::set('media.disks.collections', []);
    Config::set('media.disks.kinds', [
        'image' => 'media_images',
        'video' => 'media_videos',
        'audio' => 'media_audio',
        'document' => 'media_documents',
    ]);
    Config::set('media.disks.default', 'media');

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName(null, 'image/jpeg'))->toBe('media_images')
        ->and($resolver->resolveDiskName(null, 'video/mp4'))->toBe('media_videos')
        ->and($resolver->resolveDiskName(null, 'audio/mpeg'))->toBe('media_audio')
        ->and($resolver->resolveDiskName(null, 'application/pdf'))->toBe('media_documents');
});

test('returns filesystem instance', function () {
    Config::set('media.disks.default', 'local');

    $resolver = new StorageResolver();
    $filesystem = $resolver->filesystemForUpload(null, null);

    expect($filesystem)->toBeInstanceOf(Filesystem::class);
});

test('collection has priority over kind', function () {
    Config::set('media.disks.collections', [
        'videos' => 'media_videos_collection',
    ]);
    Config::set('media.disks.kinds', [
        'video' => 'media_videos_kind',
    ]);

    $resolver = new StorageResolver();

    // Коллекция должна иметь приоритет над kind
    expect($resolver->resolveDiskName('videos', 'video/mp4'))->toBe('media_videos_collection');
});

test('returns hardcoded fallback when config is empty', function () {
    Config::set('media.disks', []);

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName(null, null))->toBe('media');
});

test('trims collection name', function () {
    Config::set('media.disks.collections', [
        'videos' => 'media_videos',
    ]);

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName('  videos  ', null))->toBe('media_videos');
});

test('handles empty collection name', function () {
    Config::set('media.disks.collections', []);
    Config::set('media.disks.kinds', [
        'image' => 'media_images',
    ]);
    Config::set('media.disks.default', 'media');

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName('', 'image/jpeg'))->toBe('media_images');
});

