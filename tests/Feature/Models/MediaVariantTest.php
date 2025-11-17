<?php

declare(strict_types=1);

use App\Models\MediaVariant;
use App\Models\Media;
use App\Domain\Media\MediaVariantStatus;

/**
 * Feature-тесты для модели MediaVariant.
 *
 * Проверяют реальное взаимодействие модели с базой данных,
 * создание, связи и работу с enum статусами.
 */

test('variant can be created with factory', function () {
    $variant = MediaVariant::factory()->create([
        'variant' => 'thumbnail',
    ]);

    expect($variant)->toBeInstanceOf(MediaVariant::class)
        ->and($variant->variant)->toBe('thumbnail')
        ->and($variant->exists)->toBeTrue()
        ->and($variant->id)->toBeString()
        ->and(strlen($variant->id))->toBeGreaterThan(20); // ULID length check

    $this->assertDatabaseHas('media_variants', [
        'id' => $variant->id,
        'variant' => 'thumbnail',
    ]);
});

test('variant belongs to media', function () {
    $media = Media::factory()->create();
    $variant = MediaVariant::factory()->create([
        'media_id' => $media->id,
    ]);

    $variant->load('media');

    expect($variant->media)->toBeInstanceOf(Media::class)
        ->and($variant->media->id)->toBe($media->id);
});

test('variant status is cast to enum', function () {
    $variant = MediaVariant::factory()->create([
        'status' => MediaVariantStatus::Ready,
    ]);

    $variant->refresh();

    expect($variant->status)->toBeInstanceOf(MediaVariantStatus::class)
        ->and($variant->status)->toBe(MediaVariantStatus::Ready);
});

test('variant can have queued status', function () {
    $variant = MediaVariant::factory()->withStatus(MediaVariantStatus::Queued)->create();

    expect($variant->status)->toBe(MediaVariantStatus::Queued);

    $this->assertDatabaseHas('media_variants', [
        'id' => $variant->id,
        'status' => 'queued',
    ]);
});

test('variant can have processing status', function () {
    $variant = MediaVariant::factory()->withStatus(MediaVariantStatus::Processing)->create();

    expect($variant->status)->toBe(MediaVariantStatus::Processing);

    $this->assertDatabaseHas('media_variants', [
        'id' => $variant->id,
        'status' => 'processing',
    ]);
});

test('variant can have ready status', function () {
    $variant = MediaVariant::factory()->withStatus(MediaVariantStatus::Ready)->create();

    expect($variant->status)->toBe(MediaVariantStatus::Ready);

    $this->assertDatabaseHas('media_variants', [
        'id' => $variant->id,
        'status' => 'ready',
    ]);
});

test('variant can have failed status', function () {
    $variant = MediaVariant::factory()->withStatus(MediaVariantStatus::Failed)->create();

    expect($variant->status)->toBe(MediaVariantStatus::Failed);

    $this->assertDatabaseHas('media_variants', [
        'id' => $variant->id,
        'status' => 'failed',
    ]);
});

test('variant status transitions correctly', function () {
    $variant = MediaVariant::factory()->withStatus(MediaVariantStatus::Queued)->create();

    expect($variant->status)->toBe(MediaVariantStatus::Queued);

    $variant->status = MediaVariantStatus::Processing;
    $variant->save();

    expect($variant->status)->toBe(MediaVariantStatus::Processing);

    $variant->status = MediaVariantStatus::Ready;
    $variant->save();

    expect($variant->status)->toBe(MediaVariantStatus::Ready);
});

test('variant can be marked as processing', function () {
    $variant = MediaVariant::factory()->withStatus(MediaVariantStatus::Queued)->create();

    $variant->update([
        'status' => MediaVariantStatus::Processing,
        'started_at' => now(),
    ]);

    $variant->refresh();

    expect($variant->status)->toBe(MediaVariantStatus::Processing)
        ->and($variant->started_at)->not->toBeNull();
});

test('variant can be marked as ready', function () {
    $variant = MediaVariant::factory()->withStatus(MediaVariantStatus::Processing)->create();

    $variant->update([
        'status' => MediaVariantStatus::Ready,
        'finished_at' => now(),
    ]);

    $variant->refresh();

    expect($variant->status)->toBe(MediaVariantStatus::Ready)
        ->and($variant->finished_at)->not->toBeNull();
});

test('variant can be marked as failed', function () {
    $variant = MediaVariant::factory()->withStatus(MediaVariantStatus::Processing)->create();

    $variant->update([
        'status' => MediaVariantStatus::Failed,
        'error_message' => 'Processing error',
        'finished_at' => now(),
    ]);

    $variant->refresh();

    expect($variant->status)->toBe(MediaVariantStatus::Failed)
        ->and($variant->error_message)->toBe('Processing error')
        ->and($variant->finished_at)->not->toBeNull();
});

test('variant dimensions are stored correctly', function () {
    $variant = MediaVariant::factory()->create([
        'width' => 800,
        'height' => 600,
    ]);

    $variant->refresh();

    expect($variant->width)->toBe(800)
        ->and($variant->height)->toBe(600);
});

test('variant size_bytes is stored correctly', function () {
    $variant = MediaVariant::factory()->create([
        'size_bytes' => 52428, // ~50KB
    ]);

    $variant->refresh();

    expect($variant->size_bytes)->toBe(52428)
        ->and($variant->size_bytes)->toBeInt();
});

test('variant path is unique', function () {
    $path = 'variants/unique-' . uniqid() . '.jpg';

    MediaVariant::factory()->create(['path' => $path]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    MediaVariant::factory()->create(['path' => $path]);
});

test('variant name and media_id combination is unique', function () {
    $media = Media::factory()->create();

    MediaVariant::factory()->create([
        'media_id' => $media->id,
        'variant' => 'thumbnail',
    ]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    MediaVariant::factory()->create([
        'media_id' => $media->id,
        'variant' => 'thumbnail',
    ]);
});

test('variant started_at is immutable datetime', function () {
    $startedAt = now();
    $variant = MediaVariant::factory()->create([
        'started_at' => $startedAt,
    ]);

    $variant->refresh();

    expect($variant->started_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('variant finished_at is immutable datetime', function () {
    $finishedAt = now();
    $variant = MediaVariant::factory()->create([
        'finished_at' => $finishedAt,
    ]);

    $variant->refresh();

    expect($variant->finished_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

