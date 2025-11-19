<?php

declare(strict_types=1);

use App\Domain\Media\Services\StorageResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('resolves disk by kind', function () {
    Config::set('media.disks.kinds', [
        'image' => 'media_images',
        'video' => 'media_videos',
    ]);
    Config::set('media.disks.default', 'media');

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName('image/jpeg'))->toBe('media_images')
        ->and($resolver->resolveDiskName('video/mp4'))->toBe('media_videos');
});

test('detects kind from mime type', function () {
    Config::set('media.disks.kinds', [
        'image' => 'media_images',
        'video' => 'media_videos',
        'audio' => 'media_audio',
        'document' => 'media_documents',
    ]);
    Config::set('media.disks.default', 'media');

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName('image/jpeg'))->toBe('media_images')
        ->and($resolver->resolveDiskName('video/mp4'))->toBe('media_videos')
        ->and($resolver->resolveDiskName('audio/mpeg'))->toBe('media_audio')
        ->and($resolver->resolveDiskName('application/pdf'))->toBe('media_documents');
});

test('returns default disk when kind not found', function () {
    Config::set('media.disks.default', 'media_default');
    Config::set('media.disks.kinds', []);

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName('unknown/mime'))->toBe('media_default');
});

test('returns filesystem instance', function () {
    Config::set('media.disks.default', 'local');

    $resolver = new StorageResolver();
    $filesystem = $resolver->filesystemForUpload(null);

    expect($filesystem)->toBeInstanceOf(Filesystem::class);
});

test('returns hardcoded fallback when config is empty', function () {
    Config::set('media.disks', []);

    $resolver = new StorageResolver();

    expect($resolver->resolveDiskName(null))->toBe('media');
});

