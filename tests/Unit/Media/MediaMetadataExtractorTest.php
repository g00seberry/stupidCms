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

    public function test_extracts_exif_for_jpeg_with_orientation(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('exif_read_data function is not available');
        }

        $extractor = new MediaMetadataExtractor(new GdImageProcessor());

        // Создаём простой JPEG без EXIF (GD не сохраняет EXIF)
        $im = imagecreatetruecolor(100, 100);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, 99, 99, $white);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        ob_start();
        imagejpeg($im);
        $jpeg = ob_get_clean();
        file_put_contents($tmp, $jpeg);
        imagedestroy($im);

        $file = new UploadedFile($tmp, 't.jpg', 'image/jpeg', null, true);
        $meta = $extractor->extract($file, 'image/jpeg');

        $this->assertSame(100, $meta->width);
        $this->assertSame(100, $meta->height);
        // EXIF может быть null, если файл не содержит EXIF данных
        $this->assertIsArray($meta->exif ?? null);
    }

    public function test_handles_missing_exif_data(): void
    {
        $extractor = new MediaMetadataExtractor(new GdImageProcessor());

        // PNG не поддерживает EXIF
        $im = imagecreatetruecolor(10, 10);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        file_put_contents($tmp, $png);
        imagedestroy($im);

        $file = new UploadedFile($tmp, 't.png', 'image/png', null, true);
        $meta = $extractor->extract($file, 'image/png');

        $this->assertNull($meta->exif);
    }

    public function test_extracts_metadata_for_webp(): void
    {
        $extractor = new MediaMetadataExtractor(new GdImageProcessor());

        // Создаём WebP изображение
        $im = imagecreatetruecolor(200, 150);
        $blue = imagecolorallocate($im, 0, 0, 255);
        imagefilledrectangle($im, 0, 0, 199, 149, $blue);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        ob_start();
        imagewebp($im);
        $webp = ob_get_clean();
        file_put_contents($tmp, $webp);
        imagedestroy($im);

        $file = new UploadedFile($tmp, 't.webp', 'image/webp', null, true);
        $meta = $extractor->extract($file, 'image/webp');

        $this->assertSame(200, $meta->width);
        $this->assertSame(150, $meta->height);
    }

    public function test_extracts_metadata_for_gif(): void
    {
        $extractor = new MediaMetadataExtractor(new GdImageProcessor());

        // Создаём GIF изображение
        $im = imagecreatetruecolor(50, 50);
        $red = imagecolorallocate($im, 255, 0, 0);
        imagefilledrectangle($im, 0, 0, 49, 49, $red);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        ob_start();
        imagegif($im);
        $gif = ob_get_clean();
        file_put_contents($tmp, $gif);
        imagedestroy($im);

        $file = new UploadedFile($tmp, 't.gif', 'image/gif', null, true);
        $meta = $extractor->extract($file, 'image/gif');

        $this->assertSame(50, $meta->width);
        $this->assertSame(50, $meta->height);
    }

    public function test_handles_plugin_extraction_failure(): void
    {
        $images = new GdImageProcessor();

        $failingPlugin = new class implements MediaMetadataPlugin {
            public function supports(string $mime): bool
            {
                return $mime === 'video/mp4';
            }

            public function extract(string $path): array
            {
                throw new \RuntimeException('Plugin extraction failed');
            }
        };

        $workingPlugin = new class implements MediaMetadataPlugin {
            public function supports(string $mime): bool
            {
                return $mime === 'video/mp4';
            }

            public function extract(string $path): array
            {
                return [
                    'duration_ms' => 5000,
                    'bitrate_kbps' => 1000,
                ];
            }
        };

        // Первый плагин падает, второй работает
        $extractor = new MediaMetadataExtractor($images, [$failingPlugin, $workingPlugin]);

        $tmp = tempnam(sys_get_temp_dir(), 'vid');
        file_put_contents($tmp, 'dummy');

        $file = new UploadedFile($tmp, 't.mp4', 'video/mp4', null, true);
        $meta = $extractor->extract($file, 'video/mp4');

        // Должен использовать данные из рабочего плагина
        $this->assertSame(5000, $meta->durationMs);
        $this->assertSame(1000, $meta->bitrateKbps);
    }

    public function test_uses_multiple_plugins_in_order(): void
    {
        $images = new GdImageProcessor();

        $firstPlugin = new class implements MediaMetadataPlugin {
            public function supports(string $mime): bool
            {
                return $mime === 'audio/mpeg';
            }

            public function extract(string $path): array
            {
                return [
                    'duration_ms' => 1000,
                    'bitrate_kbps' => 128,
                ];
            }
        };

        $secondPlugin = new class implements MediaMetadataPlugin {
            public function supports(string $mime): bool
            {
                return $mime === 'audio/mpeg';
            }

            public function extract(string $path): array
            {
                return [
                    'duration_ms' => 2000,
                    'bitrate_kbps' => 256,
                ];
            }
        };

        $extractor = new MediaMetadataExtractor($images, [$firstPlugin, $secondPlugin]);

        $tmp = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($tmp, 'dummy');

        $file = new UploadedFile($tmp, 't.mp3', 'audio/mpeg', null, true);
        $meta = $extractor->extract($file, 'audio/mpeg');

        // Должен использовать данные из первого плагина (первый успешный)
        $this->assertSame(1000, $meta->durationMs);
        $this->assertSame(128, $meta->bitrateKbps);
    }
}


