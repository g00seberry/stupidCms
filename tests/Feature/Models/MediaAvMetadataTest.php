<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\MediaAvMetadata;

/**
 * Feature-тесты для модели MediaAvMetadata.
 *
 * Проверяют реальное взаимодействие модели с базой данных,
 * создание, связи и хранение AV метаданных.
 */

test('metadata can be created with factory', function () {
    $metadata = MediaAvMetadata::factory()->create();

    expect($metadata)->toBeInstanceOf(MediaAvMetadata::class)
        ->and($metadata->exists)->toBeTrue()
        ->and($metadata->id)->toBeString()
        ->and(strlen($metadata->id))->toBeGreaterThan(20); // ULID length check

    $this->assertDatabaseHas('media_av_metadata', [
        'id' => $metadata->id,
    ]);
});

test('metadata belongs to media', function () {
    $media = Media::factory()->create();
    $metadata = MediaAvMetadata::factory()->create([
        'media_id' => $media->id,
    ]);

    $metadata->load('media');

    expect($metadata->media)->toBeInstanceOf(Media::class)
        ->and($metadata->media->id)->toBe($media->id);
});

test('metadata stores av technical details', function () {
    $metadata = MediaAvMetadata::factory()->create([
        'duration_ms' => 120000,
        'bitrate_kbps' => 1500,
        'frame_rate' => 29.97,
        'frame_count' => 3600,
    ]);

    $metadata->refresh();

    expect($metadata->duration_ms)->toBe(120000)
        ->and($metadata->bitrate_kbps)->toBe(1500)
        ->and($metadata->frame_rate)->toBe(29.97)
        ->and($metadata->frame_count)->toBe(3600);
});

test('metadata can store video codec', function () {
    $metadata = MediaAvMetadata::factory()->create([
        'video_codec' => 'h264',
    ]);

    $metadata->refresh();

    expect($metadata->video_codec)->toBe('h264');

    $this->assertDatabaseHas('media_av_metadata', [
        'id' => $metadata->id,
        'video_codec' => 'h264',
    ]);
});

test('metadata can store audio codec', function () {
    $metadata = MediaAvMetadata::factory()->create([
        'audio_codec' => 'aac',
    ]);

    $metadata->refresh();

    expect($metadata->audio_codec)->toBe('aac');

    $this->assertDatabaseHas('media_av_metadata', [
        'id' => $metadata->id,
        'audio_codec' => 'aac',
    ]);
});

test('metadata duration_ms is cast to integer', function () {
    $metadata = MediaAvMetadata::factory()->create([
        'duration_ms' => 45000,
    ]);

    $metadata->refresh();

    expect($metadata->duration_ms)->toBeInt()
        ->and($metadata->duration_ms)->toBe(45000);
});

test('metadata bitrate_kbps is cast to integer', function () {
    $metadata = MediaAvMetadata::factory()->create([
        'bitrate_kbps' => 2500,
    ]);

    $metadata->refresh();

    expect($metadata->bitrate_kbps)->toBeInt()
        ->and($metadata->bitrate_kbps)->toBe(2500);
});

test('metadata frame_rate is cast to float', function () {
    $metadata = MediaAvMetadata::factory()->create([
        'frame_rate' => 23.976,
    ]);

    $metadata->refresh();

    expect($metadata->frame_rate)->toBeFloat()
        ->and($metadata->frame_rate)->toBe(23.976);
});

test('metadata frame_count is cast to integer', function () {
    $metadata = MediaAvMetadata::factory()->create([
        'frame_count' => 7200,
    ]);

    $metadata->refresh();

    expect($metadata->frame_count)->toBeInt()
        ->and($metadata->frame_count)->toBe(7200);
});

test('metadata can have null values for optional fields', function () {
    $metadata = MediaAvMetadata::factory()->create([
        'duration_ms' => null,
        'bitrate_kbps' => null,
        'frame_rate' => null,
        'frame_count' => null,
        'video_codec' => null,
        'audio_codec' => null,
    ]);

    $metadata->refresh();

    expect($metadata->duration_ms)->toBeNull()
        ->and($metadata->bitrate_kbps)->toBeNull()
        ->and($metadata->frame_rate)->toBeNull()
        ->and($metadata->frame_count)->toBeNull()
        ->and($metadata->video_codec)->toBeNull()
        ->and($metadata->audio_codec)->toBeNull();
});

test('metadata auto generates ULID on creation', function () {
    $media = Media::factory()->create();
    
    $metadata = new MediaAvMetadata([
        'media_id' => $media->id,
        'duration_ms' => 60000,
    ]);

    $metadata->save();

    expect($metadata->id)->toBeString()
        ->and($metadata->id)->not->toBeNull()
        ->and(strlen($metadata->id))->toBeGreaterThan(20);
});

test('metadata supports common video codecs', function () {
    $codecs = ['h264', 'h265', 'vp9', 'av1'];

    foreach ($codecs as $codec) {
        $metadata = MediaAvMetadata::factory()->create([
            'video_codec' => $codec,
        ]);

        expect($metadata->video_codec)->toBe($codec);
    }
});

test('metadata supports common audio codecs', function () {
    $codecs = ['aac', 'mp3', 'opus', 'vorbis'];

    foreach ($codecs as $codec) {
        $metadata = MediaAvMetadata::factory()->create([
            'audio_codec' => $codec,
        ]);

        expect($metadata->audio_codec)->toBe($codec);
    }
});

