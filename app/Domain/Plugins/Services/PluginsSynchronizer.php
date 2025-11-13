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

/**
 * Синхронизатор плагинов.
 *
 * Синхронизирует плагины из файловой системы в БД:
 * обнаруживает манифесты, создаёт/обновляет записи, удаляет несуществующие.
 *
 * @package App\Domain\Plugins\Services
 */
final class PluginsSynchronizer
{
    /**
     * @param \App\Domain\Plugins\Services\PluginsRouteReloader $routeReloader Перезагрузчик маршрутов
     */
    public function __construct(
        private readonly PluginsRouteReloader $routeReloader,
    ) {
    }

    /**
     * Синхронизировать плагины из файловой системы в БД.
     *
     * Обнаруживает плагины в директории, указанной в конфиге,
     * создаёт новые записи, обновляет существующие и удаляет отсутствующие.
     *
     * @return array{added: int, updated: int, removed: int, providers: list<string>} Статистика синхронизации
     * @throws \App\Domain\Plugins\Exceptions\InvalidPluginManifest Если манифест плагина невалиден
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
     * Обнаружить манифесты плагинов в директории.
     *
     * Ищет директории первого уровня и читает манифесты из них.
     *
     * @param string $rootPath Корневая директория плагинов
     * @return \Illuminate\Support\Collection<int, array{slug: string, name: string, version: string, provider: string, path: string, meta: array<string, mixed>}> Коллекция манифестов
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
     * Прочитать манифест плагина из директории.
     *
     * Ищет файлы манифеста из конфига (например, plugin.json, composer.json).
     * Поддерживает извлечение данных из composer.json (extra.stupidcms-plugin).
     *
     * @param string $directory Директория плагина
     * @return array{slug: string, name: string, version: string, provider: string, path: string, meta: array<string, mixed>} Нормализованный манифест
     * @throws \App\Domain\Plugins\Exceptions\InvalidPluginManifest Если манифест не найден или невалиден
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
     * Извлечь данные плагина из composer.json.
     *
     * Извлекает секцию extra.stupidcms-plugin и объединяет с name и version.
     *
     * @param array<string, mixed> $payload Данные из composer.json
     * @param string $manifestPath Путь к composer.json
     * @return array<string, mixed> Извлечённые данные
     * @throws \App\Domain\Plugins\Exceptions\InvalidPluginManifest Если секция extra.stupidcms-plugin отсутствует
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
     * Нормализовать манифест плагина.
     *
     * Валидирует обязательные поля (slug, name, version, provider)
     * и формирует структуру для сохранения в БД.
     *
     * @param string $directory Директория плагина
     * @param array<string, mixed> $payload Данные манифеста
     * @param string $manifestPath Путь к файлу манифеста
     * @return array{slug: string, name: string, version: string, provider: string, path: string, meta: array<string, mixed>} Нормализованный манифест
     * @throws \App\Domain\Plugins\Exceptions\InvalidPluginManifest Если поля невалидны
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

