<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Models\Media;
use Tests\Support\MediaTestCase;

final class MediaAuthTest extends MediaTestCase
{

    public function test_requires_auth_for_media_index(): void
    {
        $this->getJson('/api/v1/admin/media')->assertStatus(401);
    }

    public function test_forbidden_without_permission(): void
    {
        // Создаём обычного пользователя БЕЗ флага is_admin и без прав
        $user = $this->regularUser();
        // Используем helper, чтобы пройти JWT-авторизацию, но без прав получить 403
        $this->getJsonAsAdmin('/api/v1/admin/media', $user)->assertStatus(403);
    }

    public function test_preview_forbidden_without_permission(): void
    {
        // Создаём обычного пользователя БЕЗ флага is_admin и без прав
        $user = $this->regularUser();
        $media = Media::factory()->image()->create();
        $this->getJsonAsAdmin("/api/v1/admin/media/{$media->id}/preview", $user)->assertStatus(403);
    }
}


