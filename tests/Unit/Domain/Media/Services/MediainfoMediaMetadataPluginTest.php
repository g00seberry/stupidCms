<?php

declare(strict_types=1);

use App\Domain\Media\Services\MediainfoMediaMetadataPlugin;

test('supports video mime types', function () {
    $plugin = new MediainfoMediaMetadataPlugin();

    expect($plugin->supports('video/mp4'))->toBeTrue()
        ->and($plugin->supports('video/quicktime'))->toBeTrue()
        ->and($plugin->supports('video/webm'))->toBeTrue();
});

test('supports audio mime types', function () {
    $plugin = new MediainfoMediaMetadataPlugin();

    expect($plugin->supports('audio/mpeg'))->toBeTrue()
        ->and($plugin->supports('audio/mp4'))->toBeTrue()
        ->and($plugin->supports('audio/ogg'))->toBeTrue();
});

test('does not support image mime types', function () {
    $plugin = new MediainfoMediaMetadataPlugin();

    expect($plugin->supports('image/jpeg'))->toBeFalse()
        ->and($plugin->supports('image/png'))->toBeFalse();
});

test('does not support non-media mime types', function () {
    $plugin = new MediainfoMediaMetadataPlugin();

    expect($plugin->supports('application/pdf'))->toBeFalse()
        ->and($plugin->supports('text/plain'))->toBeFalse();
});

test('returns empty array for non-existent file', function () {
    $plugin = new MediainfoMediaMetadataPlugin();

    $result = $plugin->extract('/non/existent/path');

    expect($result)->toBeArray()->toBeEmpty();
});

test('returns empty array for empty path', function () {
    $plugin = new MediainfoMediaMetadataPlugin();

    $result = $plugin->extract('');

    expect($result)->toBeArray()->toBeEmpty();
});

test('uses custom binary path when provided', function () {
    $plugin = new MediainfoMediaMetadataPlugin('/custom/path/to/mediainfo');

    expect($plugin)->toBeInstanceOf(MediainfoMediaMetadataPlugin::class);
});

test('uses default binary when null provided', function () {
    $plugin = new MediainfoMediaMetadataPlugin(null);

    expect($plugin)->toBeInstanceOf(MediainfoMediaMetadataPlugin::class);
});

test('uses default binary when empty string provided', function () {
    $plugin = new MediainfoMediaMetadataPlugin('');

    expect($plugin)->toBeInstanceOf(MediainfoMediaMetadataPlugin::class);
});

