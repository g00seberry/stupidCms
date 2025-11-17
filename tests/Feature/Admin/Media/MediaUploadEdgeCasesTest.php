<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaUploadEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions): User
    {
        return \App\Models\User::factory()->create([
            'admin_permissions' => $permissions,
        ]);
    }

    public function test_rejects_oversized_file(): void
    {
        Storage::fake('media');
        config()->set('media.max_upload_mb', 1); // 1MB
        $admin = $this->admin(['media.create']);

        $file = UploadedFile::fake()->create('big.bin', 2 * 1024); // ~2MB

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [], ['file' => $file], $admin);
        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_rejects_disallowed_mime(): void
    {
        Storage::fake('media');
        config()->set('media.allowed_mimes', ['image/jpeg']); // png запрещён
        $admin = $this->admin(['media.create']);

        $file = UploadedFile::fake()->image('krea-edit.png', 100, 100);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [], ['file' => $file], $admin);
        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }
}


