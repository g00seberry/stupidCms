<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->postType = PostType::factory()->create(['name' => 'Page']);
});

test('generates slug from title', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=New Landing Page');

    $response->assertOk()
        ->assertJsonStructure(['base', 'unique'])
        ->assertJsonPath('base', 'new-landing-page')
        ->assertJsonPath('unique', 'new-landing-page'); // unique всегда равен base (проверка уникальности будет для route_nodes)
});


test('handles empty title', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=');

    $response->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

test('handles special characters in title', function () {
    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=' . urlencode('Hello & Goodbye! @2025'));

    $response->assertOk();
    expect($response->json('base'))->toMatch('/^[a-z0-9-]+$/');
});

test('slugify requires authentication', function () {
    $response = $this->getJson('/api/v1/admin/utils/slugify?title=Test');

    expect($response->status())->toBeIn([401, 419]);
});

test('handles very long titles', function () {
    $longTitle = str_repeat('a', 600);

    $response = $this->actingAs($this->admin)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/utils/slugify?title=' . $longTitle);

    // Should fail validation (max 500)
    $response->assertStatus(422)
        ->assertJsonValidationErrors('title');
});
