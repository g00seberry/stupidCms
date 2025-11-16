<?php

declare(strict_types=1);

namespace Tests\Unit\Media;

use App\Domain\Media\Images\GdImageProcessor;
use App\Domain\Media\Services\MediaMetadataExtractor;
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

        $this->assertSame(3, $meta['width']);
        $this->assertSame(5, $meta['height']);
        $this->assertNull($meta['exif']);
    }
}


