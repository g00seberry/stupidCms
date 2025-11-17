<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Plugins;

use App\Models\Plugin;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\FeatureTestCase;

class PluginsApiTest extends FeatureTestCase
{

    public function test_it_lists_plugins_with_filters(): void
    {
        $enabledPlugin = Plugin::factory()->enabled()->create([
            'slug' => 'alpha_plugin',
            'name' => 'Alpha Plugin',
        ]);

        $disabledPlugin = Plugin::factory()->create([
            'slug' => 'beta_plugin',
            'name' => 'Beta Plugin',
        ]);

        $admin = $this->adminWithPermissions(['plugins.read']);

        $response = $this->getJsonAsAdmin('/api/v1/admin/plugins?enabled=true&q=alpha', $admin);
        $response->assertOk();

        $response->assertJsonFragment(['slug' => $enabledPlugin->slug]);
        $response->assertJsonMissing(['slug' => $disabledPlugin->slug]);
    }

    public function test_it_enables_plugin_and_mounts_routes(): void
    {
        $admin = $this->adminWithPermissions(['plugins.read', 'plugins.toggle', 'plugins.sync']);

        Artisan::call('plugins:sync');

        $plugin = Plugin::query()->where('slug', 'example')->firstOrFail();
        $plugin->forceFill(['enabled' => false])->save();

        $enableResponse = $this->postJsonAsAdmin('/api/v1/admin/plugins/example/enable', [], $admin);
        $enableResponse->assertOk();
        $enableResponse->assertJsonFragment(['enabled' => true, 'routes_active' => true]);

        $this->assertTrue(
            Plugin::query()->where('slug', 'example')->value('enabled')
        );

        $pingResponse = $this->getJsonAsAdmin('/api/v1/example/ping', $admin);
        $pingResponse->assertOk();
        $pingResponse->assertJsonFragment(['ok' => true]);
    }

    public function test_it_disables_plugin_and_unmounts_routes(): void
    {
        $admin = $this->adminWithPermissions(['plugins.read', 'plugins.toggle', 'plugins.sync']);

        Artisan::call('plugins:sync');
        $plugin = Plugin::query()->where('slug', 'example')->firstOrFail();
        $plugin->forceFill(['enabled' => true])->save();

        $disableResponse = $this->postJsonAsAdmin('/api/v1/admin/plugins/example/disable', [], $admin);
        $disableResponse->assertOk();
        $disableResponse->assertJsonFragment(['enabled' => false, 'routes_active' => false]);

        $this->assertFalse(
            Plugin::query()->where('slug', 'example')->value('enabled')
        );

        $pingResponse = $this->getJsonAsAdmin('/api/v1/example/ping', $admin);
        $pingResponse->assertStatus(404);
    }

    public function test_it_syncs_filesystem_into_database(): void
    {
        $admin = $this->adminWithPermissions(['plugins.sync', 'plugins.read']);

        Plugin::query()->delete();

        $response = $this->postJsonAsAdmin('/api/v1/admin/plugins/sync', [], $admin);
        $response->assertAccepted();

        $response->assertJsonPath('summary.added', 1);
        $response->assertJsonPath('summary.providers.0', 'Plugins\\Example\\ExamplePluginServiceProvider');

        $this->assertDatabaseHas('plugins', [
            'slug' => 'example',
            'enabled' => false,
        ]);
    }

    public function test_it_handles_duplicate_enable_disable(): void
    {
        $admin = $this->adminWithPermissions(['plugins.toggle', 'plugins.sync', 'plugins.read']);

        Artisan::call('plugins:sync');
        $plugin = Plugin::query()->where('slug', 'example')->firstOrFail();

        $this->postJsonAsAdmin('/api/v1/admin/plugins/example/enable', [], $admin)->assertOk();

        $this->postJsonAsAdmin('/api/v1/admin/plugins/example/enable', [], $admin)
            ->assertStatus(409)
            ->assertJsonFragment(['title' => 'Plugin already enabled']);

        $this->postJsonAsAdmin('/api/v1/admin/plugins/example/disable', [], $admin)->assertOk();

        $this->postJsonAsAdmin('/api/v1/admin/plugins/example/disable', [], $admin)
            ->assertStatus(409)
            ->assertJsonFragment(['title' => 'Plugin already disabled']);
    }

    public function test_it_requires_permissions(): void
    {
        $user = User::factory()->create();

        $this->getJsonAsAdmin('/api/v1/admin/plugins', $user)
            ->assertForbidden();
    }
}

