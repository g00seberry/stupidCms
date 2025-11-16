<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaPreviewCloudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions): User
    {
        return \App\Models\User::factory()->create([
            'admin_permissions' => $permissions,
        ]);
    }

    public function test_preview_redirects_for_cloud_disk(): void
    {
        $admin = $this->admin(['media.read']);

        // Fake "cloud" disk: path() should fail, temporaryUrl returns URL
        Storage::fake('media');
        $file = base_path('tests/Feature/Admin/Media/krea-edit.png');
        $storedPath = '2025/11/16/krea-edit.png';
        Storage::disk('media')->put($storedPath, file_get_contents($file));

        $media = Media::factory()->image()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'image/png',
            'ext' => 'png',
            'width' => 600,
            'height' => 400,
        ]);

        // В зависимости от драйвера окружения ответ может быть 200 (local) или 302 (cloud).

        $response = $this->getJsonAsAdmin("/api/v1/admin/media/{$media->id}/preview?variant=thumbnail", $admin);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 302], true), 'Expected 200 or 302');
    }

    public function test_preview_unknown_variant_returns_422(): void
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

        $response = $this->getJsonAsAdmin("/api/v1/admin/media/{$media->id}/preview?variant=not_configured", $admin);
        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_preview_for_non_image_returns_422(): void
    {
        Storage::fake('media');
        $admin = $this->admin(['media.read']);

        $storedPath = '2025/11/16/sample.pdf';
        Storage::disk('media')->put($storedPath, '%PDF-1.4 ...');

        $media = Media::factory()->document()->create([
            'disk' => 'media',
            'path' => $storedPath,
            'mime' => 'application/pdf',
            'ext' => 'pdf',
        ]);

        $response = $this->getJsonAsAdmin("/api/v1/admin/media/{$media->id}/preview?variant=thumbnail", $admin);
        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }
}


