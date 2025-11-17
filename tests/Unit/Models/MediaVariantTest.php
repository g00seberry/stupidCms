<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Domain\Media\MediaVariantStatus;
use App\Models\Media;
use App\Models\MediaVariant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тесты для модели MediaVariant.
 */
final class MediaVariantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Проверка отношения belongsTo Media.
     */
    public function test_belongs_to_media(): void
    {
        $media = Media::factory()->create();
        $variant = MediaVariant::factory()->create([
            'media_id' => $media->id,
        ]);

        $this->assertInstanceOf(Media::class, $variant->media);
        $this->assertSame($media->id, $variant->media->id);
    }

    /**
     * Наличие enum статуса (Processing, Ready, Failed).
     */
    public function test_has_status_enum(): void
    {
        $media = Media::factory()->create();

        $processingVariant = MediaVariant::factory()->withStatus(MediaVariantStatus::Processing)->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
        ]);
        $readyVariant = MediaVariant::factory()->withStatus(MediaVariantStatus::Ready)->create([
            'media_id' => $media->id,
            'variant' => 'preview',
        ]);
        $failedVariant = MediaVariant::factory()->withStatus(MediaVariantStatus::Failed)->create([
            'media_id' => $media->id,
            'variant' => 'large',
        ]);
        $queuedVariant = MediaVariant::factory()->withStatus(MediaVariantStatus::Queued)->create([
            'media_id' => $media->id,
            'variant' => 'medium',
        ]);

        $this->assertInstanceOf(MediaVariantStatus::class, $processingVariant->status);
        $this->assertSame(MediaVariantStatus::Processing, $processingVariant->status);
        $this->assertSame(MediaVariantStatus::Ready, $readyVariant->status);
        $this->assertSame(MediaVariantStatus::Failed, $failedVariant->status);
        $this->assertSame(MediaVariantStatus::Queued, $queuedVariant->status);
    }

    /**
     * Отслеживание временных меток генерации (started_at, finished_at).
     */
    public function test_tracks_generation_timestamps(): void
    {
        $media = Media::factory()->create();
        $startedAt = CarbonImmutable::now('UTC')->subMinutes(5);
        $finishedAt = CarbonImmutable::now('UTC');

        $variant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ]);

        $variant->refresh();

        $this->assertInstanceOf(CarbonImmutable::class, $variant->started_at);
        $this->assertInstanceOf(CarbonImmutable::class, $variant->finished_at);
        $this->assertSame($startedAt->timestamp, $variant->started_at->timestamp);
        $this->assertSame($finishedAt->timestamp, $variant->finished_at->timestamp);
    }
}

