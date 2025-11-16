<?php

declare(strict_types=1);

namespace Tests\Unit\Media;

use App\Domain\Media\Images\GdImageProcessor;
use App\Domain\Media\Images\ImageProcessor;
use PHPUnit\Framework\TestCase;

final class ImageProcessorGdTest extends TestCase
{
    public function test_opens_resizes_and_encodes_image_via_gd(): void
    {
        /** @var ImageProcessor $proc */
        $proc = new GdImageProcessor();

        // Сгенерируем 2x1 PNG в памяти
        $im = imagecreatetruecolor(2, 1);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, 1, 0, $transparent);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);
        $this->assertIsString($png);
        $this->assertNotSame('', $png);

        $img = $proc->open($png);
        $this->assertSame(2, $proc->width($img));
        $this->assertSame(1, $proc->height($img));

        $resized = $proc->resize($img, 4, 2);
        $this->assertSame(4, $proc->width($resized));
        $this->assertSame(2, $proc->height($resized));

        $encoded = $proc->encode($resized, 'webp', 80);
        $this->assertIsString($encoded['data']);
        $this->assertNotSame('', $encoded['data']);
        $this->assertContains($encoded['extension'], ['webp', 'jpg']); // fallback may happen
        $this->assertContains($encoded['mime'], ['image/webp', 'image/jpeg']);

        $proc->destroy($resized);
    }
}


