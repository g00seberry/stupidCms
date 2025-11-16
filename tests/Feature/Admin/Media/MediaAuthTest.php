<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth_for_media_index(): void
    {
        $this->getJson('/api/v1/admin/media')->assertStatus(401);
    }

    public function test_forbidden_without_permission(): void
    {
        $user = \App\Models\User::factory()->create(['admin_permissions' => []]);
        // Используем helper, чтобы пройти JWT-авторизацию, но без прав получить 403
        $this->getJsonAsAdmin('/api/v1/admin/media', $user)->assertStatus(403);
    }

    public function test_preview_forbidden_without_permission(): void
    {
        Storage::fake('media');
        $user = \App\Models\User::factory()->create(['admin_permissions' => []]);
        $media = Media::factory()->image()->create();
        $this->getJsonAsAdmin("/api/v1/admin/media/{$media->id}/preview", $user)->assertStatus(403);
    }
}


