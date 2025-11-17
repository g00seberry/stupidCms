<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use Illuminate\Http\UploadedFile;
use Tests\Support\MediaTestCase;

final class MediaUploadEdgeCasesTest extends MediaTestCase
{
    public function test_rejects_oversized_file(): void
    {
        config()->set('media.max_upload_mb', 1); // 1MB
        $admin = $this->admin(['media.create']);

        $file = UploadedFile::fake()->create('big.bin', 2 * 1024); // ~2MB

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [], ['file' => $file], $admin);
        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_rejects_disallowed_mime(): void
    {
        config()->set('media.allowed_mimes', ['image/jpeg']); // png запрещён
        $admin = $this->admin(['media.create']);

        // Создаем временный PNG файл
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tempPath = tempnam(sys_get_temp_dir(), 'png_');
        file_put_contents($tempPath, $pngData);
        
        // Создаем UploadedFile из готового файла
        $file = new \Illuminate\Http\UploadedFile($tempPath, 'test.png', 'image/png', null, true);

        $response = $this->postMultipartAsAdmin('/api/v1/admin/media', [], ['file' => $file], $admin);
        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
        
        // Удаляем временный файл
        @unlink($tempPath);
    }
}


