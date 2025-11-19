<?php

declare(strict_types=1);

use App\Domain\Media\Services\EssenceMediaMetadataPlugin;

test('supports video mime types', function () {
    $plugin = new EssenceMediaMetadataPlugin();

    expect($plugin->supports('video/mp4'))->toBeTrue()
        ->and($plugin->supports('video/quicktime'))->toBeTrue()
        ->and($plugin->supports('video/webm'))->toBeTrue();
});

test('supports audio mime types', function () {
    $plugin = new EssenceMediaMetadataPlugin();

    expect($plugin->supports('audio/mpeg'))->toBeTrue()
        ->and($plugin->supports('audio/mp4'))->toBeTrue()
        ->and($plugin->supports('audio/ogg'))->toBeTrue();
});

test('does not support image mime types', function () {
    $plugin = new EssenceMediaMetadataPlugin();

    expect($plugin->supports('image/jpeg'))->toBeFalse()
        ->and($plugin->supports('image/png'))->toBeFalse();
});

test('does not support non-media mime types', function () {
    $plugin = new EssenceMediaMetadataPlugin();

    expect($plugin->supports('application/pdf'))->toBeFalse()
        ->and($plugin->supports('text/plain'))->toBeFalse();
});

test('returns empty array for non-existent file', function () {
    $plugin = new EssenceMediaMetadataPlugin();

    $result = $plugin->extract('/non/existent/path');

    expect($result)->toBeArray()->toBeEmpty();
});

test('returns empty array for empty path', function () {
    $plugin = new EssenceMediaMetadataPlugin();

    $result = $plugin->extract('');

    expect($result)->toBeArray()->toBeEmpty();
});

test('uses custom getID3 instance when provided', function () {
    $getID3 = new \getID3();
    $plugin = new EssenceMediaMetadataPlugin($getID3);

    expect($plugin)->toBeInstanceOf(EssenceMediaMetadataPlugin::class);
});

test('creates new getID3 instance when null provided', function () {
    $plugin = new EssenceMediaMetadataPlugin(null);

    expect($plugin)->toBeInstanceOf(EssenceMediaMetadataPlugin::class);
});

test('extracts metadata structure correctly', function () {
    $plugin = new EssenceMediaMetadataPlugin();

    // Создаём временный файл для тестирования
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.mp4';
    file_put_contents($tempFile, '');

    try {
        $result = $plugin->extract($tempFile);

        expect($result)->toBeArray()
            ->toHaveKeys(['duration_ms', 'bitrate_kbps', 'frame_rate', 'frame_count', 'video_codec', 'audio_codec']);

        // Проверяем типы значений
        if ($result['duration_ms'] !== null) {
            expect($result['duration_ms'])->toBeInt();
        }
        if ($result['bitrate_kbps'] !== null) {
            expect($result['bitrate_kbps'])->toBeInt();
        }
        if ($result['frame_rate'] !== null) {
            expect($result['frame_rate'])->toBeFloat();
        }
        if ($result['frame_count'] !== null) {
            expect($result['frame_count'])->toBeInt();
        }
        if ($result['video_codec'] !== null) {
            expect($result['video_codec'])->toBeString();
        }
        if ($result['audio_codec'] !== null) {
            expect($result['audio_codec'])->toBeString();
        }
    } finally {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
});

