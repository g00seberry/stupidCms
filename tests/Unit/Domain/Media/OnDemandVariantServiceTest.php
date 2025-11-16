<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media;

use App\Domain\Media\Services\OnDemandVariantService;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OnDemandVariantServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_variant_generates_synchronously_in_tests_even_if_queue_not_sync(): void
    {
        Storage::fake('media');
        config()->set('queue.default', 'database'); // не sync
        \Illuminate\Support\Facades\Queue::fake();

        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        $service = app(OnDemandVariantService::class);
        $variant = $service->ensureVariant($media, 'thumbnail');

        // В тестах generation выполняется синхронно, job не пушится
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
        $this->assertSame('thumbnail', $variant->variant);
    }
}


