<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class GenerateApiDoc extends Command
{
    protected $signature = 'docs:api';
    protected $description = 'Generate API reference documentation';

    public function handle(): int
    {
        $this->info('Generating API reference documentation...');

        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
            ->map(function ($route): array {
                $methods = collect($route->methods())
                    ->reject(fn (string $method) => $method === 'HEAD')
                    ->unique()
                    ->values()
                    ->all();

                if (empty($methods)) {
                    return [];
                }

                $uri = '/' . trim($route->uri(), '/');
                $category = $this->categorize($uri);

                return [
                    'methods' => $methods,
                    'uri' => $uri,
                    'name' => $route->getName(),
                    'category' => $category,
                    'resource' => $this->extractResource($category, $uri),
                ];
            })
            ->filter()
            ->values();

        if ($routes->isEmpty()) {
            $this->warn('No API routes found. Skipping docs generation.');
            return self::SUCCESS;
        }

        $document = $this->buildDocument($routes);
        $targetPath = base_path('docs/30-reference/api.md');
        file_put_contents($targetPath, $document);

        $this->info('✓ Generated: docs/30-reference/api.md');

        return self::SUCCESS;
    }

    private function buildDocument(Collection $routes): string
    {
        $now = now('UTC');

        $grouped = $routes
            ->groupBy('category')
            ->map(function (Collection $categoryRoutes): Collection {
                return $categoryRoutes
                    ->sortBy('uri')
                    ->groupBy('resource')
                    ->map(fn (Collection $resourceRoutes) => $resourceRoutes->sortBy('uri')->values());
            });

        $frontMatter = <<<YAML
---
owner: "@backend-team"
system_of_record: "generated"
review_cycle_days: 14
last_reviewed: {$now->toDateString()}
related_code:
    - "app/Http/Controllers/*.php"
    - "app/Http/Requests/*.php"
---
YAML;

        $doc = $frontMatter . "\n\n";
        $doc .= "# API Reference\n\n";
        $doc .= "> ⚠️ **Auto-generated**. Do not edit manually. Run `composer docs:gen` or `php artisan docs:api`.\n\n";
        $doc .= '_Last generated: ' . $now->toDateTimeString() . " UTC_\n\n";

        $categories = [
            'public' => [
                'title' => 'Public API (`/api/*`)',
            ],
            'admin' => [
                'title' => 'Admin API (`/api/v1/admin/*`)',
            ],
            'auth' => [
                'title' => 'Auth (`/api/auth/*`)',
            ],
        ];

        foreach ($categories as $key => $meta) {
            if (! $grouped->has($key)) {
                continue;
            }

            $doc .= '## ' . $meta['title'] . "\n\n";

            $resources = $grouped->get($key);

            foreach ($resources as $resource => $resourceRoutes) {
                $heading = $resource === 'root'
                    ? 'Root'
                    : Str::headline(Str::slug($resource, ' '));

                $doc .= '### ' . $heading . "\n\n";

                foreach ($resourceRoutes as $route) {
                    $methods = collect($route['methods'])
                        ->map(fn ($method) => '`' . $method . '`')
                        ->implode(' / ');

                    $name = $route['name']
                        ? '`' . $route['name'] . '`'
                        : '_(unnamed)_';

                    $doc .= sprintf(
                        "- %s `%s` — %s\n",
                        $methods,
                        $route['uri'],
                        $name
                    );
                }

                $doc .= "\n";
            }
        }

        return rtrim($doc) . "\n";
    }

    private function categorize(string $uri): string
    {
        $trimmed = ltrim($uri, '/');

        return match (true) {
            str_starts_with($trimmed, 'api/v1/admin/') => 'admin',
            str_starts_with($trimmed, 'api/admin/') => 'admin',
            str_starts_with($trimmed, 'api/v1/auth/') => 'auth',
            str_starts_with($trimmed, 'api/auth/') => 'auth',
            default => 'public',
        };
    }

    private function extractResource(string $category, string $uri): string
    {
        $segments = explode('/', trim($uri, '/'));

        // Remove the shared "api" prefix
        array_shift($segments);

        if ($category === 'admin') {
            if (! empty($segments) && preg_match('/^v\d+$/', $segments[0])) {
                array_shift($segments);
            }

            if (! empty($segments) && $segments[0] === 'admin') {
                array_shift($segments);
            }
        } elseif ($category === 'auth') {
            if (! empty($segments) && preg_match('/^v\d+$/', $segments[0])) {
                array_shift($segments);
            }

            if (! empty($segments) && $segments[0] === 'auth') {
                array_shift($segments);
            }
        } else {
            if (! empty($segments) && preg_match('/^v\d+$/', $segments[0])) {
                array_shift($segments);
            }
        }

        return $segments[0] ?? 'root';
    }
}


