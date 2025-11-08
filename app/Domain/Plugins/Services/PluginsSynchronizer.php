<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Services;

use App\Domain\Plugins\Events\PluginsSynced;
use App\Domain\Plugins\Exceptions\InvalidPluginManifest;
use App\Models\Plugin;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

final class PluginsSynchronizer
{
    public function __construct(
        private readonly PluginsRouteReloader $routeReloader,
    ) {
    }

    /**
     * @return array{added: int, updated: int, removed: int, providers: list<string>}
     */
    public function sync(): array
    {
        $rootPath = (string) config('plugins.path');

        if ($rootPath === '' || ! is_dir($rootPath)) {
            return ['added' => 0, 'updated' => 0, 'removed' => 0, 'providers' => []];
        }

        $now = Carbon::now('UTC');

        $manifests = $this->discoverManifests($rootPath);

        $added = 0;
        $updated = 0;
        $providers = [];
        $slugs = [];

        foreach ($manifests as $manifest) {
            $slugs[] = $manifest['slug'];

            /** @var Plugin|null $plugin */
            $plugin = Plugin::query()->where('slug', $manifest['slug'])->first();

            if ($plugin === null) {
                $plugin = new Plugin();
                $plugin->forceFill(['id' => (string) Str::ulid()]);
                $added++;
            } else {
                $updated++;
            }

            $plugin->forceFill([
                'slug' => $manifest['slug'],
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'provider_fqcn' => $manifest['provider'],
                'path' => $manifest['path'],
                'meta_json' => $manifest['meta'],
                'last_synced_at' => $now,
            ]);

            $plugin->save();

            $providers[] = $manifest['provider'];
        }

        $removed = (int) Plugin::query()
            ->whereNotIn('slug', $slugs)
            ->delete();

        Event::dispatch(new PluginsSynced($added, $updated, $removed, $providers));

        $this->routeReloader->reload();

        return [
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'providers' => $providers,
        ];
    }

    /**
     * @return Collection<int, array{slug: string, name: string, version: string, provider: string, path: string, meta: array<string, mixed>}>
     */
    private function discoverManifests(string $rootPath): Collection
    {
        $finder = (new Finder())
            ->directories()
            ->depth('== 0')
            ->in($rootPath);

        $manifests = collect();

        foreach ($finder as $directory) {
            $manifests->push($this->readManifest($directory->getRealPath()));
        }

        return $manifests->sortBy('slug')->values();
    }

    /**
     * @return array{slug: string, name: string, version: string, provider: string, path: string, meta: array<string, mixed>}
     */
    private function readManifest(string $directory): array
    {
        $manifestFiles = (array) config('plugins.manifest', []);

        foreach ($manifestFiles as $candidate) {
            $manifestPath = $directory . DIRECTORY_SEPARATOR . $candidate;
            if (! is_file($manifestPath)) {
                continue;
            }

            $payload = json_decode((string) file_get_contents($manifestPath), true);

            if (! is_array($payload)) {
                throw InvalidPluginManifest::forPath($manifestPath, 'File is not a valid JSON object.');
            }

            if (str_ends_with($candidate, 'composer.json')) {
                $payload = $this->extractComposerManifest($payload, $manifestPath);
            }

            return $this->normalizeManifest($directory, $payload, $manifestPath);
        }

        throw InvalidPluginManifest::forPath($directory, 'Manifest file not found.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function extractComposerManifest(array $payload, string $manifestPath): array
    {
        $extra = Arr::get($payload, 'extra.stupidcms-plugin');

        if (! is_array($extra)) {
            throw InvalidPluginManifest::forPath($manifestPath, 'Missing extra.stupidcms-plugin section.');
        }

        return $extra + Arr::only($payload, ['name', 'version']);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{slug: string, name: string, version: string, provider: string, path: string, meta: array<string, mixed>}
     */
    private function normalizeManifest(string $directory, array $payload, string $manifestPath): array
    {
        $slug = Arr::get($payload, 'slug');
        $name = Arr::get($payload, 'name');
        $version = Arr::get($payload, 'version');
        $provider = Arr::get($payload, 'provider');
        $routes = Arr::get($payload, 'routes', []);

        if (! is_string($slug) || ! preg_match('/^[a-z0-9_][a-z0-9_.-]{1,63}$/', $slug)) {
            throw InvalidPluginManifest::forPath($manifestPath, 'Slug missing or invalid.');
        }

        if (! is_string($name) || $name === '') {
            throw InvalidPluginManifest::forPath($manifestPath, 'Name missing or invalid.');
        }

        if (! is_string($version) || $version === '') {
            throw InvalidPluginManifest::forPath($manifestPath, 'Version missing or invalid.');
        }

        if (! is_string($provider) || $provider === '') {
            throw InvalidPluginManifest::forPath($manifestPath, 'Provider missing or invalid.');
        }

        $meta = [
            'routes' => is_array($routes) ? array_values(array_filter($routes, 'is_string')) : [],
            'manifest' => basename($manifestPath),
        ];

        return [
            'slug' => $slug,
            'name' => $name,
            'version' => $version,
            'provider' => $provider,
            'path' => $directory,
            'meta' => $meta,
        ];
    }
}

