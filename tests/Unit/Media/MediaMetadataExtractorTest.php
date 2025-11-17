<?php

declare(strict_types=1);

namespace Tests\Unit\Media;

use App\Domain\Media\Images\GdImageProcessor;
use App\Domain\Media\Services\MediaMetadataExtractor;
use App\Domain\Media\Services\MediaMetadataPlugin;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

final class MediaMetadataExtractorTest extends TestCase
{
    public function test_extracts_width_height_for_png_via_processor(): void
    {
        $extractor = new MediaMetadataExtractor(new GdImageProcessor());

        // Сгенерируем 3x5 PNG в памяти и сохраним во временный файл
        $im = imagecreatetruecolor(3, 5);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, 2, 4, $white);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        file_put_contents($tmp, $png);
        imagedestroy($im);

        $file = new UploadedFile($tmp, 't.png', 'image/png', null, true);
        $meta = $extractor->extract($file, 'image/png');

        $this->assertSame(3, $meta->width);
        $this->assertSame(5, $meta->height);
        $this->assertNull($meta->exif);
    }

    public function test_uses_plugins_for_video_duration_and_bitrate(): void
    {
        $images = new GdImageProcessor();

        $plugin = new class implements MediaMetadataPlugin {
            public function supports(string $mime): bool
            {
                return $mime === 'video/mp4';
            }

            public function extract(string $path): array
            {
                return [
                    'duration_ms' => 123_456,
                    'bitrate_kbps' => 789,
                    'frame_rate' => 29.97,
                    'frame_count' => 3_700,
                    'video_codec' => 'h264',
                    'audio_codec' => 'aac',
                ];
            }
        };

        $extractor = new MediaMetadataExtractor($images, [$plugin]);

        $tmp = tempnam(sys_get_temp_dir(), 'vid');
        file_put_contents($tmp, 'dummy');

        $file = new UploadedFile($tmp, 't.mp4', 'video/mp4', null, true);
        $meta = $extractor->extract($file, 'video/mp4');

        $this->assertNull($meta->width);
        $this->assertNull($meta->height);
        $this->assertNull($meta->exif);
        $this->assertSame(123_456, $meta->durationMs);
        $this->assertSame(789, $meta->bitrateKbps);
        $this->assertSame(29.97, $meta->frameRate);
        $this->assertSame(3_700, $meta->frameCount);
        $this->assertSame('h264', $meta->videoCodec);
        $this->assertSame('aac', $meta->audioCodec);
    }
}


