<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Media;
use App\Models\MediaMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тесты для модели MediaMetadata.
 */
final class MediaMetadataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Проверка отношения belongsTo Media.
     */
    public function test_belongs_to_media(): void
    {
        $media = Media::factory()->create();
        $metadata = MediaMetadata::factory()->create([
            'media_id' => $media->id,
        ]);

        $this->assertInstanceOf(Media::class, $metadata->media);
        $this->assertSame($media->id, $metadata->media->id);
    }

    /**
     * Сохранение нормализованных AV метаданных.
     */
    public function test_stores_normalized_av_metadata(): void
    {
        $media = Media::factory()->create([
            'mime' => 'video/mp4',
        ]);

        $metadata = MediaMetadata::factory()->create([
            'media_id' => $media->id,
            'duration_ms' => 125000,
            'bitrate_kbps' => 2500,
            'frame_rate' => 29.97,
            'frame_count' => 3750,
            'video_codec' => 'h264',
            'audio_codec' => 'aac',
        ]);

        $this->assertSame(125000, $metadata->duration_ms);
        $this->assertSame(2500, $metadata->bitrate_kbps);
        $this->assertSame(29.97, $metadata->frame_rate);
        $this->assertSame(3750, $metadata->frame_count);
        $this->assertSame('h264', $metadata->video_codec);
        $this->assertSame('aac', $metadata->audio_codec);
    }
}

