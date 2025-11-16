<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Domain\Media\Actions\MediaStoreAction;
use App\Domain\Media\Services\MediaMetadataExtractor;
use App\Domain\Media\Services\StorageResolver;
use App\Models\Media;
use App\Models\MediaMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaTechnicalMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_normalized_av_metadata_in_separate_table(): void
    {
        Storage::fake('media');

        $extractor = new class extends MediaMetadataExtractor {
            public function __construct()
            {
                // Переопределяем конструктор, зависимости не нужны в фейке.
            }

            public function extract(UploadedFile $file, ?string $mime = null): array
            {
                return [
                    'width' => null,
                    'height' => null,
                    'duration_ms' => 123_456,
                    'exif' => null,
                    'bitrate_kbps' => 789,
                    'frame_rate' => 29.97,
                    'frame_count' => 3_700,
                    'video_codec' => 'h264',
                    'audio_codec' => 'aac',
                ];
            }
        };

        $action = new MediaStoreAction(
            $extractor,
            new StorageResolver(),
        );

        $file = UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4');

        $media = $action->execute($file, [
            'title' => 'Test video',
        ]);

        /** @var \App\Models\Media $media */
        $this->assertInstanceOf(Media::class, $media);

        $this->assertDatabaseHas('media_metadata', [
            'media_id' => $media->id,
            'duration_ms' => 123_456,
            'bitrate_kbps' => 789,
            'frame_count' => 3_700,
            'video_codec' => 'h264',
            'audio_codec' => 'aac',
        ]);

        /** @var \App\Models\MediaMetadata $meta */
        $meta = MediaMetadata::where('media_id', $media->id)->firstOrFail();

        $this->assertSame(29.97, $meta->frame_rate);
    }
}


