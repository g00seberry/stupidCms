<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $this->withoutMiddleware();
});

test('GET /api/v1/admin/entries/{entry}/preview возвращает Entry даже если status=draft', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'status' => 'draft',
        'published_at' => null,
    ]);

    $response = $this->getJson("/api/v1/admin/entries/{$entry->id}/preview");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'entry' => [
                'id',
                'title',
                'status',
                'published_at',
                'data_json',
                'template_override',
                'created_at',
                'updated_at',
            ],
            'post_type' => [
                'id',
                'name',
                'template',
            ],
            'blueprint',
        ])
        ->assertJson([
            'entry' => [
                'id' => $entry->id,
                'status' => 'draft',
                'published_at' => null,
            ],
        ]);
});

test('GET /api/v1/admin/entries/{entry}/preview возвращает Entry с future published_at', function () {
    $postType = PostType::factory()->create();
    $futureDate = now()->addDays(7);
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'status' => 'published',
        'published_at' => $futureDate,
    ]);

    $response = $this->getJson("/api/v1/admin/entries/{$entry->id}/preview");

    $response->assertStatus(200)
        ->assertJson([
            'entry' => [
                'id' => $entry->id,
                'status' => 'published',
            ],
        ]);
});

test('GET /api/v1/admin/entries/{entry}/preview требует авторизации', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
    ]);

    // Не авторизованный запрос
    auth()->logout();
    $this->withoutMiddleware();

    $response = $this->getJson("/api/v1/admin/entries/{$entry->id}/preview");

    // authorize() выбрасывает AuthorizationException (403), когда пользователь не авторизован
    $response->assertStatus(403);
});

test('GET /api/v1/admin/entries/{entry}/preview требует права view', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
    ]);

    // Пользователь без прав
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);
    $this->withoutMiddleware();

    $response = $this->getJson("/api/v1/admin/entries/{$entry->id}/preview");

    $response->assertStatus(403);
});

test('GET /api/v1/admin/entries/{entry}/preview возвращает 404 для несуществующего Entry', function () {
    $response = $this->getJson('/api/v1/admin/entries/99999/preview');

    $response->assertStatus(404);
});

test('GET /api/v1/admin/entries/{entry}/preview возвращает Entry с blueprint', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
    ]);

    $response = $this->getJson("/api/v1/admin/entries/{$entry->id}/preview");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'entry',
            'post_type',
            'blueprint',
        ]);
});

