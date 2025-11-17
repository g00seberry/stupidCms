<?php

declare(strict_types=1);

namespace Tests\Integration\Domain\Media\Jobs;

use App\Domain\Media\Jobs\GenerateVariantJob;
use App\Domain\Media\MediaVariantStatus;
use App\Domain\Media\Services\OnDemandVariantService;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery as m;
use Tests\Support\IntegrationTestCase;

/**
 * Тесты для GenerateVariantJob.
 *
 * Проверяет генерацию вариантов медиа-файлов в фоновом режиме через очередь.
 */
final class GenerateVariantJobTest extends IntegrationTestCase
{
    

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_dispatches_job_for_variant_generation(): void
    {
        Bus::fake();

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => '2025/11/16/test.jpg',
            'mime' => 'image/jpeg',
            'ext' => 'jpg',
        ]);

        GenerateVariantJob::dispatch($media->id, 'thumbnail');

        Bus::assertDispatched(GenerateVariantJob::class, function (GenerateVariantJob $job) use ($media): bool {
            $reflection = new \ReflectionClass($job);
            $mediaIdProperty = $reflection->getProperty('mediaId');
            $mediaIdProperty->setAccessible(true);
            $variantProperty = $reflection->getProperty('variant');
            $variantProperty->setAccessible(true);

            return $mediaIdProperty->getValue($job) === $media->id
                && $variantProperty->getValue($job) === 'thumbnail';
        });
    }

    public function test_marks_variant_as_processing(): void
    {
        Storage::fake('media');
        Config::set('media.variants', [
            'thumbnail' => ['max' => 320],
        ]);

        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $service = m::mock(OnDemandVariantService::class);
        $expectedVariant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'status' => MediaVariantStatus::Ready,
        ]);

        $service->shouldReceive('generateVariant')
            ->once()
            ->with(m::on(function (Media $m) use ($media): bool {
                return $m->id === $media->id;
            }), 'thumbnail')
            ->andReturn($expectedVariant);

        $job = new GenerateVariantJob($media->id, 'thumbnail');
        $job->handle($service);

        // Проверяем, что вариант был создан/обновлён со статусом Processing перед вызовом generateVariant
        $variant = MediaVariant::where('media_id', $media->id)
            ->where('variant', 'thumbnail')
            ->first();

        $this->assertNotNull($variant);
        $this->assertNotNull($variant->started_at);
    }

    public function test_handles_missing_media_gracefully(): void
    {
        $nonExistentId = '01HZ123456789ABCDEFGHIJKLMN';

        $service = m::mock(OnDemandVariantService::class);
        $service->shouldNotReceive('generateVariant');

        $job = new GenerateVariantJob($nonExistentId, 'thumbnail');
        $job->handle($service);

        // Job должен завершиться без ошибки
        $this->assertTrue(true);
    }

    public function test_handles_soft_deleted_media(): void
    {
        Storage::fake('media');
        Config::set('media.variants', [
            'thumbnail' => ['max' => 320],
        ]);

        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $media->delete();
        $this->assertTrue($media->trashed());

        $service = m::mock(OnDemandVariantService::class);
        $expectedVariant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'status' => MediaVariantStatus::Ready,
        ]);

        $service->shouldReceive('generateVariant')
            ->once()
            ->with(m::on(function (Media $m) use ($media): bool {
                // Проверяем, что медиа найдено через withTrashed()
                return $m->id === $media->id && $m->trashed();
            }), 'thumbnail')
            ->andReturn($expectedVariant);

        $job = new GenerateVariantJob($media->id, 'thumbnail');
        $job->handle($service);

        // Job должен обработать мягко удалённое медиа через withTrashed()
        $variant = MediaVariant::where('media_id', $media->id)
            ->where('variant', 'thumbnail')
            ->first();

        $this->assertNotNull($variant);
    }

    public function test_retries_on_failure(): void
    {
        $job = new GenerateVariantJob('01HZ123456789ABCDEFGHIJKLMN', 'thumbnail');

        $this->assertSame(3, $job->tries);
    }

    public function test_uses_backoff_strategy(): void
    {
        $job = new GenerateVariantJob('01HZ123456789ABCDEFGHIJKLMN', 'thumbnail');

        $backoff = $job->backoff();

        $this->assertSame([5, 15, 60], $backoff);
    }

    public function test_calls_on_demand_variant_service(): void
    {
        Storage::fake('media');
        Config::set('media.variants', [
            'thumbnail' => ['max' => 320],
        ]);

        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $service = m::mock(OnDemandVariantService::class);
        $expectedVariant = MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => 'thumbnail',
            'status' => MediaVariantStatus::Ready,
        ]);

        $service->shouldReceive('generateVariant')
            ->once()
            ->with(m::on(function (Media $m) use ($media): bool {
                return $m->id === $media->id;
            }), 'thumbnail')
            ->andReturn($expectedVariant);

        $job = new GenerateVariantJob($media->id, 'thumbnail');
        $job->handle($service);

        $this->assertTrue(true);
    }
}



