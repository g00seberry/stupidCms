<?php

declare(strict_types=1);

namespace Tests\Unit\Media;

use App\Domain\Media\Images\GlideImageProcessor;
use App\Domain\Media\Images\ImageProcessor;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use PHPUnit\Framework\TestCase;

final class ImageProcessorGlideTest extends TestCase
{
    public function test_opens_resizes_and_encodes_image_via_glide_when_available(): void
    {
        if (! class_exists(ImageManager::class)) {
            $this->markTestSkipped('Intervention Image is not installed.');
        }

        /** @var ImageProcessor $proc */
        $proc = new GlideImageProcessor(new ImageManager(driver: new GdDriver()));

        // Сгенерируем 2x2 PNG (через GD, как источник)
        $im = imagecreatetruecolor(2, 2);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, 1, 1, $white);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);
        $this->assertIsString($png);
        $this->assertNotSame('', $png);

        $img = $proc->open($png);
        $this->assertSame(2, $proc->width($img));
        $this->assertSame(2, $proc->height($img));

        $resized = $proc->resize($img, 4, 4);
        $this->assertSame(4, $proc->width($resized));
        $this->assertSame(4, $proc->height($resized));

        $encoded = $proc->encode($resized, 'jpg', 85);
        $this->assertIsString($encoded['data']);
        $this->assertNotSame('', $encoded['data']);
        $this->assertSame('jpg', $encoded['extension']);
        $this->assertSame('image/jpeg', $encoded['mime']);
    }
}


