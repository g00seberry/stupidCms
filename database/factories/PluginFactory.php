<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plugin>
 */
final class PluginFactory extends Factory
{
    protected $model = Plugin::class;

    public function definition(): array
    {
        $slug = strtolower('plugin_' . $this->faker->unique()->lexify('????'));

        return [
            'id' => (string) Str::ulid(),
            'slug' => $slug,
            'name' => Str::headline($slug),
            'version' => sprintf('%d.%d.%d', $this->faker->numberBetween(0, 3), $this->faker->numberBetween(0, 10), $this->faker->numberBetween(0, 20)),
            'provider_fqcn' => 'Plugins\\' . Str::studly($slug) . '\\PluginServiceProvider',
            'path' => base_path('plugins/' . $slug),
            'enabled' => false,
            'meta_json' => [],
            'last_synced_at' => now(),
        ];
    }

    public function enabled(): self
    {
        return $this->state(fn () => ['enabled' => true]);
    }
}

