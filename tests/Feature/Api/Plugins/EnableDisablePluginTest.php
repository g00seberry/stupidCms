<?php

declare(strict_types=1);

use App\Domain\Plugins\Contracts\RouteReloader;
use App\Models\Plugin;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    
    // Mock RouteReloader interface to prevent actual route reloading
    $this->mock(RouteReloader::class, function ($mock) {
        $mock->shouldReceive('reload')->andReturn();
    });
});

// ENABLE tests
test('admin can enable plugin', function () {
    $plugin = Plugin::factory()->create(['enabled' => false, 'slug' => 'test-plugin']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/plugins/{$plugin->slug}/enable");

    $response->assertOk()
        ->assertJsonPath('slug', 'test-plugin')
        ->assertJsonPath('enabled', true);

    expect($plugin->fresh()->enabled)->toBeTrue();
});

test('enabling already enabled plugin returns conflict', function () {
    $plugin = Plugin::factory()->create(['enabled' => true, 'slug' => 'already-enabled']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/plugins/{$plugin->slug}/enable");

    $response->assertStatus(409)
        ->assertJsonPath('code', 'PLUGIN_ALREADY_ENABLED');
});

test('enable returns 404 for non-existent plugin', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/plugins/non-existent-plugin/enable');

    $response->assertNotFound()
        ->assertJsonPath('code', 'PLUGIN_NOT_FOUND');
});

test('enable triggers route reload', function () {
    $mock = $this->mock(RouteReloader::class);
    $mock->shouldReceive('reload')->once();

    $plugin = Plugin::factory()->create(['enabled' => false, 'slug' => 'test']);

    actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/plugins/{$plugin->slug}/enable");
});

// DISABLE tests
test('admin can disable plugin', function () {
    $plugin = Plugin::factory()->create(['enabled' => true, 'slug' => 'test-plugin']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/plugins/{$plugin->slug}/disable");

    $response->assertOk()
        ->assertJsonPath('slug', 'test-plugin')
        ->assertJsonPath('enabled', false);

    expect($plugin->fresh()->enabled)->toBeFalse();
});

test('disabling already disabled plugin returns conflict', function () {
    $plugin = Plugin::factory()->create(['enabled' => false, 'slug' => 'already-disabled']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/plugins/{$plugin->slug}/disable");

    $response->assertStatus(409)
        ->assertJsonPath('code', 'PLUGIN_ALREADY_DISABLED');
});

test('disable returns 404 for non-existent plugin', function () {
    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/plugins/non-existent-plugin/disable');

    $response->assertNotFound()
        ->assertJsonPath('code', 'PLUGIN_NOT_FOUND');
});

test('disable triggers route reload', function () {
    $mock = $this->mock(RouteReloader::class);
    $mock->shouldReceive('reload')->once();

    $plugin = Plugin::factory()->create(['enabled' => true, 'slug' => 'test']);

    actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/plugins/{$plugin->slug}/disable");
});

test('enable returns plugin resource with correct structure', function () {
    $plugin = Plugin::factory()->create(['enabled' => false, 'slug' => 'structured']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/plugins/{$plugin->slug}/enable");

    $response->assertOk()
        ->assertJsonStructure([
            'slug',
            'name',
            'version',
            'enabled',
            'provider',
            'routes_active',
            'last_synced_at',
        ]);
});

test('disable returns plugin resource with correct structure', function () {
    $plugin = Plugin::factory()->create(['enabled' => true, 'slug' => 'structured']);

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/plugins/{$plugin->slug}/disable");

    $response->assertOk()
        ->assertJsonStructure([
            'slug',
            'name',
            'version',
            'enabled',
            'provider',
            'routes_active',
            'last_synced_at',
        ]);
});

test('enable dispatches plugin enabled event', function () {
    Event::fake();

    $plugin = Plugin::factory()->create(['enabled' => false, 'slug' => 'test']);

    actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/plugins/{$plugin->slug}/enable");

    Event::assertDispatched(\App\Domain\Plugins\Events\PluginEnabled::class, function ($event) use ($plugin) {
        return $event->plugin->id === $plugin->id;
    });
});

test('disable dispatches plugin disabled event', function () {
    Event::fake();

    $plugin = Plugin::factory()->create(['enabled' => true, 'slug' => 'test']);

    actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/plugins/{$plugin->slug}/disable");

    Event::assertDispatched(\App\Domain\Plugins\Events\PluginDisabled::class, function ($event) use ($plugin) {
        return $event->plugin->id === $plugin->id;
    });
});

