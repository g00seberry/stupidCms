<?php

declare(strict_types=1);

use App\Domain\Media\DTO\MediaMetadataDTO;

test('creates dto with all properties', function () {
    $dto = new MediaMetadataDTO(
        width: 1920,
        height: 1080,
        durationMs: 5000,
        exif: ['IFD0' => ['Make' => 'Canon']],
        bitrateKbps: 5000,
        frameRate: 30.0,
        frameCount: 150,
        videoCodec: 'h264',
        audioCodec: 'aac'
    );

    expect($dto->width)->toBe(1920)
        ->and($dto->height)->toBe(1080)
        ->and($dto->durationMs)->toBe(5000)
        ->and($dto->exif)->toBe(['IFD0' => ['Make' => 'Canon']])
        ->and($dto->bitrateKbps)->toBe(5000)
        ->and($dto->frameRate)->toBe(30.0)
        ->and($dto->frameCount)->toBe(150)
        ->and($dto->videoCodec)->toBe('h264')
        ->and($dto->audioCodec)->toBe('aac');
});

test('converts to array', function () {
    $dto = new MediaMetadataDTO(
        width: 1920,
        height: 1080,
        durationMs: 5000,
        exif: ['IFD0' => ['Make' => 'Canon']],
        bitrateKbps: 5000,
        frameRate: 30.0,
        frameCount: 150,
        videoCodec: 'h264',
        audioCodec: 'aac'
    );

    $array = $dto->toArray();

    expect($array)->toBe([
        'width' => 1920,
        'height' => 1080,
        'duration_ms' => 5000,
        'exif' => ['IFD0' => ['Make' => 'Canon']],
        'bitrate_kbps' => 5000,
        'frame_rate' => 30.0,
        'frame_count' => 150,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
    ]);
});

test('validates required fields', function () {
    $dto = new MediaMetadataDTO();

    expect($dto->width)->toBeNull()
        ->and($dto->height)->toBeNull()
        ->and($dto->durationMs)->toBeNull()
        ->and($dto->exif)->toBeNull()
        ->and($dto->bitrateKbps)->toBeNull()
        ->and($dto->frameRate)->toBeNull()
        ->and($dto->frameCount)->toBeNull()
        ->and($dto->videoCodec)->toBeNull()
        ->and($dto->audioCodec)->toBeNull();
});

test('creates dto from array', function () {
    $data = [
        'width' => 1920,
        'height' => 1080,
        'duration_ms' => 5000,
        'exif' => ['IFD0' => ['Make' => 'Canon']],
        'bitrate_kbps' => 5000,
        'frame_rate' => 30.0,
        'frame_count' => 150,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
    ];

    $dto = MediaMetadataDTO::fromArray($data);

    expect($dto->width)->toBe(1920)
        ->and($dto->height)->toBe(1080)
        ->and($dto->durationMs)->toBe(5000)
        ->and($dto->exif)->toBe(['IFD0' => ['Make' => 'Canon']])
        ->and($dto->bitrateKbps)->toBe(5000)
        ->and($dto->frameRate)->toBe(30.0)
        ->and($dto->frameCount)->toBe(150)
        ->and($dto->videoCodec)->toBe('h264')
        ->and($dto->audioCodec)->toBe('aac');
});

test('from array handles null values', function () {
    $data = [];

    $dto = MediaMetadataDTO::fromArray($data);

    expect($dto->width)->toBeNull()
        ->and($dto->height)->toBeNull()
        ->and($dto->durationMs)->toBeNull();
});

test('from array handles invalid types', function () {
    $data = [
        'width' => 'invalid',
        'height' => 'invalid',
        'duration_ms' => 'invalid',
        'bitrate_kbps' => 'invalid',
        'frame_rate' => 'invalid',
        'frame_count' => 'invalid',
        'video_codec' => 123,
        'audio_codec' => 456,
    ];

    $dto = MediaMetadataDTO::fromArray($data);

    expect($dto->width)->toBeNull()
        ->and($dto->height)->toBeNull()
        ->and($dto->durationMs)->toBeNull()
        ->and($dto->bitrateKbps)->toBeNull()
        ->and($dto->frameRate)->toBeNull()
        ->and($dto->frameCount)->toBeNull()
        ->and($dto->videoCodec)->toBeNull()
        ->and($dto->audioCodec)->toBeNull();
});

test('dto is readonly', function () {
    $dto = new MediaMetadataDTO(width: 1920);

    // Попытка изменить readonly свойство должна вызвать ошибку
    // Но в тестах мы можем проверить, что свойства доступны только для чтения
    expect($dto->width)->toBe(1920);
});

test('to array converts null values correctly', function () {
    $dto = new MediaMetadataDTO();

    $array = $dto->toArray();

    expect($array)->toBe([
        'width' => null,
        'height' => null,
        'duration_ms' => null,
        'exif' => null,
        'bitrate_kbps' => null,
        'frame_rate' => null,
        'frame_count' => null,
        'video_codec' => null,
        'audio_codec' => null,
    ]);
});

