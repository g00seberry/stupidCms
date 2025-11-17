<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Тесты нормализации таблицы media.
 *
 * Проверяет:
 * - Уникальность по (disk, path)
 * - Работу с JSONB для exif_json (PostgreSQL)
 * - Индексацию checksum_sha256
 */
final class MediaTableNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enforces_unique_constraint_on_disk_and_path(): void
    {
        // Создаём первую запись
        $media1 = Media::factory()->create([
            'disk' => 'media',
            'path' => '2024/01/01/test.jpg',
        ]);

        $this->assertDatabaseHas('media', [
            'id' => $media1->id,
            'disk' => 'media',
            'path' => '2024/01/01/test.jpg',
        ]);

        // Пытаемся создать запись с тем же path, но другим disk - должно пройти
        $media2 = Media::factory()->create([
            'disk' => 'backup',
            'path' => '2024/01/01/test.jpg',
        ]);

        $this->assertDatabaseHas('media', [
            'id' => $media2->id,
            'disk' => 'backup',
            'path' => '2024/01/01/test.jpg',
        ]);

        // Пытаемся создать запись с тем же disk и path - должно упасть
        $this->expectException(\Illuminate\Database\QueryException::class);

        Media::factory()->create([
            'disk' => 'media',
            'path' => '2024/01/01/test.jpg',
        ]);
    }

    public function test_allows_same_path_on_different_disks(): void
    {
        $disks = ['media', 'backup', 'archive'];

        foreach ($disks as $disk) {
            $media = Media::factory()->create([
                'disk' => $disk,
                'path' => '2024/01/01/same.jpg',
            ]);

            $this->assertDatabaseHas('media', [
                'id' => $media->id,
                'disk' => $disk,
                'path' => '2024/01/01/same.jpg',
            ]);
        }

        $this->assertDatabaseCount('media', 3);
    }

    public function test_exif_json_stored_and_retrieved_correctly(): void
    {
        $exifData = [
            'Camera' => 'Canon EOS 5D',
            'ISO' => 400,
            'Aperture' => 'f/2.8',
            'ShutterSpeed' => '1/125',
            'GPS' => [
                'Latitude' => 55.7558,
                'Longitude' => 37.6173,
            ],
        ];

        $media = Media::factory()->create([
            'exif_json' => $exifData,
        ]);

        $media->refresh();

        $this->assertIsArray($media->exif_json);
        $this->assertSame('Canon EOS 5D', $media->exif_json['Camera']);
        $this->assertSame(400, $media->exif_json['ISO']);
        $this->assertIsArray($media->exif_json['GPS']);
        $this->assertSame(55.7558, $media->exif_json['GPS']['Latitude']);
    }

    public function test_exif_json_supports_null(): void
    {
        $media = Media::factory()->create([
            'exif_json' => null,
        ]);

        $media->refresh();

        $this->assertNull($media->exif_json);
    }

    public function test_checksum_sha256_is_indexed(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->markTestSkipped('SQLite не поддерживает проверку индексов через information_schema');
        }

        $hasIndex = match ($driver) {
            'pgsql' => (int) DB::selectOne(
                "SELECT COUNT(*) as count FROM pg_indexes WHERE tablename = 'media' AND indexdef LIKE '%checksum_sha256%'"
            )->count > 0,
            'mysql' => (int) DB::selectOne(
                "SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'media' AND column_name = 'checksum_sha256'"
            )->count > 0,
            default => false,
        };

        $this->assertTrue($hasIndex, 'Индекс на checksum_sha256 должен существовать');
    }

    public function test_can_query_by_checksum_sha256_efficiently(): void
    {
        $checksum = hash('sha256', 'test-content');

        $media1 = Media::factory()->create([
            'checksum_sha256' => $checksum,
        ]);

        $media2 = Media::factory()->create([
            'checksum_sha256' => $checksum,
        ]);

        // Запрос по индексированному полю должен работать быстро
        $results = Media::where('checksum_sha256', $checksum)->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains(fn ($media) => $media->id === $media1->id));
        $this->assertTrue($results->contains(fn ($media) => $media->id === $media2->id));
    }

    public function test_exif_json_uses_jsonb_on_postgresql(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Тест только для PostgreSQL');
        }

        $exifData = ['test' => 'value'];

        $media = Media::factory()->create([
            'exif_json' => $exifData,
        ]);

        // Проверяем, что колонка имеет тип jsonb
        $columnType = DB::selectOne(
            "SELECT data_type FROM information_schema.columns WHERE table_name = 'media' AND column_name = 'exif_json'"
        );

        $this->assertSame('jsonb', $columnType->data_type);
    }
}

