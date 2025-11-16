<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Domain\Media\MediaVariantStatus;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaVariantStatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions): User
    {
        return \App\Models\User::factory()->create([
            'admin_permissions' => $permissions,
        ]);
    }

    public function test_status_processing_and_ready_are_set(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read']);

        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
        ]);

        // В тестах ensureVariant выполняет синхронную генерацию
        $response = $this->getJsonAsAdmin("/api/v1/admin/media/{$media->id}/preview?variant=thumbnail", $admin);
        $response->assertStatus(200);

        $variant = MediaVariant::where('media_id', $media->id)->where('variant', 'thumbnail')->first();
        $this->assertNotNull($variant);
        $this->assertSame(MediaVariantStatus::Ready, $variant->status);
        $this->assertNotNull($variant->finished_at);
    }
}


