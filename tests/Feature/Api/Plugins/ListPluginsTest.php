<?php

declare(strict_types=1);

use App\Models\Plugin;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
});

// LIST tests
test('admin can list plugins', function () {
    Plugin::factory()->count(3)->create();

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['slug', 'name', 'version', 'enabled', 'provider', 'routes_active', 'last_synced_at'],
            ],
            'links',
            'meta',
        ])
        ->assertJsonPath('meta.total', 3);
});

test('plugins list is paginated', function () {
    Plugin::factory()->count(30)->create();

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins?per_page=10');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 30)
        ->assertJsonCount(10, 'data');
});

test('plugins can be filtered by enabled status', function () {
    Plugin::factory()->create(['enabled' => true, 'slug' => 'enabled-plugin']);
    Plugin::factory()->create(['enabled' => false, 'slug' => 'disabled-plugin']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins?enabled=true');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.slug', 'enabled-plugin')
        ->assertJsonPath('data.0.enabled', true);
});

test('plugins can be filtered by disabled status', function () {
    Plugin::factory()->create(['enabled' => true, 'slug' => 'enabled-plugin']);
    Plugin::factory()->create(['enabled' => false, 'slug' => 'disabled-plugin']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins?enabled=false');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.slug', 'disabled-plugin')
        ->assertJsonPath('data.0.enabled', false);
});

test('plugins can be searched by slug', function () {
    Plugin::factory()->create(['slug' => 'seo-tools', 'name' => 'SEO Tools']);
    Plugin::factory()->create(['slug' => 'analytics', 'name' => 'Analytics Plugin']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins?q=seo');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.slug', 'seo-tools');
});

test('plugins can be searched by name', function () {
    Plugin::factory()->create(['slug' => 'seo-tools', 'name' => 'SEO Tools']);
    Plugin::factory()->create(['slug' => 'analytics', 'name' => 'Analytics Plugin']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins?q=Analytics');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.slug', 'analytics');
});

test('plugins can be sorted by name', function () {
    Plugin::factory()->create(['name' => 'Zebra Plugin', 'slug' => 'zebra']);
    Plugin::factory()->create(['name' => 'Alpha Plugin', 'slug' => 'alpha']);
    Plugin::factory()->create(['name' => 'Beta Plugin', 'slug' => 'beta']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins?sort=name&order=asc');

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'Alpha Plugin')
        ->assertJsonPath('data.1.name', 'Beta Plugin')
        ->assertJsonPath('data.2.name', 'Zebra Plugin');
});

test('plugins can be sorted by slug', function () {
    Plugin::factory()->create(['slug' => 'z-plugin', 'name' => 'Z']);
    Plugin::factory()->create(['slug' => 'a-plugin', 'name' => 'A']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins?sort=slug&order=asc');

    $response->assertOk()
        ->assertJsonPath('data.0.slug', 'a-plugin')
        ->assertJsonPath('data.1.slug', 'z-plugin');
});

test('plugins can be sorted by version', function () {
    Plugin::factory()->create(['version' => '2.0.0', 'slug' => 'v2']);
    Plugin::factory()->create(['version' => '1.0.0', 'slug' => 'v1']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins?sort=version&order=asc');

    $response->assertOk()
        ->assertJsonPath('data.0.version', '1.0.0')
        ->assertJsonPath('data.1.version', '2.0.0');
});

test('plugins include routes_active flag', function () {
    $plugin = Plugin::factory()->create(['enabled' => true]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins');

    $response->assertOk()
        ->assertJsonPath('data.0.routes_active', false); // Provider not actually loaded in tests
});

test('plugins include all metadata fields', function () {
    $plugin = Plugin::factory()->create([
        'slug' => 'test-plugin',
        'name' => 'Test Plugin',
        'version' => '1.2.3',
        'enabled' => true,
        'last_synced_at' => now(),
    ]);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/plugins');

    $response->assertOk()
        ->assertJsonPath('data.0.slug', 'test-plugin')
        ->assertJsonPath('data.0.name', 'Test Plugin')
        ->assertJsonPath('data.0.version', '1.2.3')
        ->assertJsonPath('data.0.enabled', true)
        ->assertJsonPath('data.0.provider', $plugin->provider_fqcn)
        ->assertJsonPath('data.0.routes_active', false);

    expect($response->json('data.0.last_synced_at'))->not->toBeNull();
});

