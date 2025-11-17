<?php

declare(strict_types=1);

use App\Domain\Plugins\Contracts\PluginsSynchronizerInterface;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
});

test('admin can sync plugins', function () {
    // Mock PluginsSynchronizer to return summary without actual FS scanning
    $this->mock(PluginsSynchronizerInterface::class, function ($mock) {
        $mock->shouldReceive('sync')->once()->andReturn([
            'added' => ['new-plugin'],
            'updated' => ['existing-plugin'],
            'removed' => [],
            'providers' => ['Plugins\\NewPlugin\\ServiceProvider'],
        ]);
    });

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/plugins/sync');

    $response->assertStatus(202)
        ->assertJsonPath('status', 'accepted')
        ->assertJsonPath('summary.added', ['new-plugin'])
        ->assertJsonPath('summary.updated', ['existing-plugin'])
        ->assertJsonPath('summary.removed', [])
        ->assertJsonPath('summary.providers', ['Plugins\\NewPlugin\\ServiceProvider']);
});

test('sync returns accepted status code 202', function () {
    $this->mock(PluginsSynchronizerInterface::class, function ($mock) {
        $mock->shouldReceive('sync')->once()->andReturn([
            'added' => [],
            'updated' => [],
            'removed' => [],
            'providers' => [],
        ]);
    });

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/plugins/sync');

    $response->assertStatus(202);
});

test('sync returns summary with added plugins', function () {
    $this->mock(PluginsSynchronizerInterface::class, function ($mock) {
        $mock->shouldReceive('sync')->once()->andReturn([
            'added' => ['plugin-a', 'plugin-b'],
            'updated' => [],
            'removed' => [],
            'providers' => [],
        ]);
    });

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/plugins/sync');

    $response->assertStatus(202)
        ->assertJsonPath('summary.added', ['plugin-a', 'plugin-b']);
});

test('sync returns summary with updated plugins', function () {
    $this->mock(PluginsSynchronizerInterface::class, function ($mock) {
        $mock->shouldReceive('sync')->once()->andReturn([
            'added' => [],
            'updated' => ['plugin-x'],
            'removed' => [],
            'providers' => [],
        ]);
    });

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/plugins/sync');

    $response->assertStatus(202)
        ->assertJsonPath('summary.updated', ['plugin-x']);
});

test('sync returns summary with removed plugins', function () {
    $this->mock(PluginsSynchronizerInterface::class, function ($mock) {
        $mock->shouldReceive('sync')->once()->andReturn([
            'added' => [],
            'updated' => [],
            'removed' => ['old-plugin'],
            'providers' => [],
        ]);
    });

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/plugins/sync');

    $response->assertStatus(202)
        ->assertJsonPath('summary.removed', ['old-plugin']);
});

test('sync returns summary with providers', function () {
    $this->mock(PluginsSynchronizerInterface::class, function ($mock) {
        $mock->shouldReceive('sync')->once()->andReturn([
            'added' => [],
            'updated' => [],
            'removed' => [],
            'providers' => ['Plugins\\ProviderA\\ServiceProvider', 'Plugins\\ProviderB\\ServiceProvider'],
        ]);
    });

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/plugins/sync');

    $response->assertStatus(202)
        ->assertJsonPath('summary.providers', [
            'Plugins\\ProviderA\\ServiceProvider',
            'Plugins\\ProviderB\\ServiceProvider',
        ]);
});

test('sync returns correct structure', function () {
    $this->mock(PluginsSynchronizerInterface::class, function ($mock) {
        $mock->shouldReceive('sync')->once()->andReturn([
            'added' => [],
            'updated' => [],
            'removed' => [],
            'providers' => [],
        ]);
    });

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/plugins/sync');

    $response->assertStatus(202)
        ->assertJsonStructure([
            'status',
            'summary' => [
                'added',
                'updated',
                'removed',
                'providers',
            ],
        ]);
});

test('sync handles empty summary gracefully', function () {
    $this->mock(PluginsSynchronizerInterface::class, function ($mock) {
        $mock->shouldReceive('sync')->once()->andReturn([
            'added' => [],
            'updated' => [],
            'removed' => [],
            'providers' => [],
        ]);
    });

    $response = actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/plugins/sync');

    $response->assertStatus(202)
        ->assertJsonPath('summary.added', [])
        ->assertJsonPath('summary.updated', [])
        ->assertJsonPath('summary.removed', [])
        ->assertJsonPath('summary.providers', []);
});

