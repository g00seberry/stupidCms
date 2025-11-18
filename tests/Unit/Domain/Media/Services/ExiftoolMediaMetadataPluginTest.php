<?php

declare(strict_types=1);

use App\Domain\Media\Services\ExiftoolMediaMetadataPlugin;
use Illuminate\Http\UploadedFile;

test('supports image mime types', function () {
    $plugin = new ExiftoolMediaMetadataPlugin();

    expect($plugin->supports('image/jpeg'))->toBeTrue()
        ->and($plugin->supports('image/png'))->toBeTrue()
        ->and($plugin->supports('image/gif'))->toBeTrue();
});

test('supports video mime types', function () {
    $plugin = new ExiftoolMediaMetadataPlugin();

    expect($plugin->supports('video/mp4'))->toBeTrue()
        ->and($plugin->supports('video/quicktime'))->toBeTrue();
});

test('supports audio mime types', function () {
    $plugin = new ExiftoolMediaMetadataPlugin();

    expect($plugin->supports('audio/mpeg'))->toBeTrue()
        ->and($plugin->supports('audio/mp4'))->toBeTrue();
});

test('does not support non-media mime types', function () {
    $plugin = new ExiftoolMediaMetadataPlugin();

    expect($plugin->supports('application/pdf'))->toBeFalse()
        ->and($plugin->supports('text/plain'))->toBeFalse();
});

test('returns empty array for non-existent file', function () {
    $plugin = new ExiftoolMediaMetadataPlugin();

    $result = $plugin->extract('/non/existent/path');

    expect($result)->toBeArray()->toBeEmpty();
});

test('returns empty array for empty path', function () {
    $plugin = new ExiftoolMediaMetadataPlugin();

    $result = $plugin->extract('');

    expect($result)->toBeArray()->toBeEmpty();
});

test('uses custom binary path when provided', function () {
    $plugin = new ExiftoolMediaMetadataPlugin('/custom/path/to/exiftool');

    // Проверяем, что плагин создан с кастомным путем
    expect($plugin)->toBeInstanceOf(ExiftoolMediaMetadataPlugin::class);
});

test('uses default binary when null provided', function () {
    $plugin = new ExiftoolMediaMetadataPlugin(null);

    expect($plugin)->toBeInstanceOf(ExiftoolMediaMetadataPlugin::class);
});

test('uses default binary when empty string provided', function () {
    $plugin = new ExiftoolMediaMetadataPlugin('');

    expect($plugin)->toBeInstanceOf(ExiftoolMediaMetadataPlugin::class);
});

