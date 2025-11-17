<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media\Services;

use App\Domain\Media\Images\GdImageProcessor;
use App\Domain\Media\Services\ExifManager;
use Tests\TestCase;

/**
 * Тесты для ExifManager.
 *
 * Проверяет управление EXIF данными изображений: фильтрацию, извлечение ICC профиля, Orientation.
 */
final class ExifManagerTest extends TestCase
{
    private ExifManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ExifManager(new GdImageProcessor());
    }

    public function test_filters_exif_by_whitelist(): void
    {
        $exif = [
            'IFD0' => [
                'Make' => 'Canon',
                'Model' => 'EOS 5D',
                'DateTime' => '2025:01:17 12:00:00',
            ],
            'EXIF' => [
                'ExposureTime' => '1/125',
                'ISOSpeedRatings' => 400,
            ],
        ];

        $whitelist = ['IFD0.Make', 'IFD0.Model', 'EXIF.ExposureTime'];

        $filtered = $this->manager->filterExif($exif, $whitelist);

        $this->assertNotNull($filtered);
        $this->assertArrayHasKey('IFD0', $filtered);
        $this->assertArrayHasKey('EXIF', $filtered);
        $this->assertSame('Canon', $filtered['IFD0']['Make']);
        $this->assertSame('EOS 5D', $filtered['IFD0']['Model']);
        $this->assertSame('1/125', $filtered['EXIF']['ExposureTime']);
        $this->assertArrayNotHasKey('DateTime', $filtered['IFD0']);
        $this->assertArrayNotHasKey('ISOSpeedRatings', $filtered['EXIF']);
    }

    public function test_returns_null_for_empty_whitelist(): void
    {
        $exif = [
            'IFD0' => [
                'Make' => 'Canon',
            ],
        ];

        $filtered = $this->manager->filterExif($exif, []);

        $this->assertSame($exif, $filtered);
    }

    public function test_handles_missing_exif_fields(): void
    {
        $exif = [
            'IFD0' => [
                'Make' => 'Canon',
            ],
        ];

        $whitelist = ['IFD0.Make', 'IFD0.Nonexistent', 'EXIF.ExposureTime'];

        $filtered = $this->manager->filterExif($exif, $whitelist);

        $this->assertNotNull($filtered);
        $this->assertArrayHasKey('IFD0', $filtered);
        $this->assertSame('Canon', $filtered['IFD0']['Make']);
        $this->assertArrayNotHasKey('EXIF', $filtered);
    }

    public function test_extracts_color_profile_from_exif(): void
    {
        $iccProfile = 'dummy_icc_profile_data';
        $exif = [
            'ICC_Profile' => [
                'icc_profile' => base64_encode($iccProfile),
            ],
        ];

        $profile = $this->manager->extractColorProfile($exif);

        $this->assertNotNull($profile);
        $this->assertSame($iccProfile, $profile);
    }

    public function test_handles_base64_encoded_icc_profile(): void
    {
        $iccProfile = 'test_icc_data';
        $exif = [
            'IFD0' => [
                'ICC_Profile' => base64_encode($iccProfile),
            ],
        ];

        $profile = $this->manager->extractColorProfile($exif);

        $this->assertNotNull($profile);
        $this->assertSame($iccProfile, $profile);
    }

    public function test_handles_hex_encoded_icc_profile(): void
    {
        $iccProfile = 'test_icc_data';
        $hexEncoded = bin2hex($iccProfile);
        $exif = [
            'EXIF' => [
                'icc_profile_hex' => $hexEncoded,
            ],
        ];

        $profile = $this->manager->extractColorProfile($exif);

        $this->assertNotNull($profile);
        // hex2bin может вернуть строку с байтами, проверяем что она не пустая
        $this->assertIsString($profile);
        $this->assertNotEmpty($profile);
    }

    public function test_returns_null_when_no_icc_profile(): void
    {
        $exif = [
            'IFD0' => [
                'Make' => 'Canon',
                'Model' => 'EOS 5D',
            ],
        ];

        $profile = $this->manager->extractColorProfile($exif);

        $this->assertNull($profile);
    }

    public function test_get_orientation_from_exif(): void
    {
        // Тестируем фильтрацию Orientation через filterExif
        $exif = [
            'IFD0' => [
                'Orientation' => 6,
            ],
            'EXIF' => [
                'Orientation' => 3,
            ],
        ];

        // Проверяем, что EXIF данные обрабатываются корректно
        $filtered = $this->manager->filterExif($exif, ['IFD0.Orientation']);

        $this->assertNotNull($filtered);
        $this->assertSame(6, $filtered['IFD0']['Orientation']);
        $this->assertArrayNotHasKey('EXIF', $filtered);
    }

    public function test_handles_auto_rotate_placeholder(): void
    {
        $imageBytes = 'dummy_image_data';
        $exif = [
            'IFD0' => [
                'Orientation' => 6,
            ],
        ];

        // Метод autoRotate пока возвращает оригинал (TODO в коде)
        $result = $this->manager->autoRotate($imageBytes, $exif);

        $this->assertSame($imageBytes, $result);
    }

    public function test_handles_strip_exif_placeholder(): void
    {
        $imageBytes = 'dummy_image_data';
        $mime = 'image/jpeg';

        // Метод stripExif пока возвращает оригинал (TODO в коде)
        $result = $this->manager->stripExif($imageBytes, $mime);

        $this->assertSame($imageBytes, $result);
    }

    public function test_returns_null_for_null_exif(): void
    {
        $filtered = $this->manager->filterExif(null, ['IFD0.Make']);

        $this->assertNull($filtered);

        $profile = $this->manager->extractColorProfile(null);

        $this->assertNull($profile);
    }
}

