<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class GenerateRoutesDoc extends Command
{
    protected $signature = 'docs:routes';
    protected $description = 'Generate routes documentation';

    public function handle(): int
    {
        $this->info('Generating routes documentation...');

        $routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => implode(', ', $route->middleware()),
            ];
        })->values()->all();

        // JSON для программного использования
        $jsonPath = base_path('docs/_generated/routes.json');
        file_put_contents($jsonPath, json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info("✓ Generated: docs/_generated/routes.json");

        // Markdown для читаемости
        $mdPath = base_path('docs/_generated/routes.md');
        $markdown = $this->generateMarkdown($routes);
        file_put_contents($mdPath, $markdown);
        $this->info("✓ Generated: docs/_generated/routes.md");

        return self::SUCCESS;
    }

    private function generateMarkdown(array $routes): string
    {
        $md = "# Routes\n\n";
        $md .= "> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:routes` to update.\n\n";
        $md .= "_Last generated: " . now()->toDateTimeString() . "_\n\n";

        // Group by prefix
        $grouped = collect($routes)->groupBy(function ($route) {
            $uri = $route['uri'];
            if (str_starts_with($uri, 'api/admin/')) return 'Admin API';
            if (str_starts_with($uri, 'api/')) return 'Public API';
            if (str_starts_with($uri, 'auth/')) return 'Auth';
            return 'Web';
        });

        foreach ($grouped as $group => $groupRoutes) {
            $md .= "## {$group}\n\n";
            $md .= "| Method | URI | Name | Action | Middleware |\n";
            $md .= "|--------|-----|------|--------|------------|\n";

            foreach ($groupRoutes as $route) {
                $md .= sprintf(
                    "| %s | `%s` | %s | %s | %s |\n",
                    $route['method'],
                    $route['uri'],
                    $route['name'] ?: '-',
                    $this->formatAction($route['action']),
                    $route['middleware'] ?: '-'
                );
            }

            $md .= "\n";
        }

        return $md;
    }

    private function formatAction(string $action): string
    {
        // Красивее форматируем action
        if (str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action);
            $controller = class_basename($controller);
            return "`{$controller}@{$method}`";
        }

        if (str_contains($action, 'Closure')) {
            return '_Closure_';
        }

        return "`{$action}`";
    }
}

