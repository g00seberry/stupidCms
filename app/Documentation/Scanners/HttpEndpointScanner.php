<?php

declare(strict_types=1);

namespace App\Documentation\Scanners;

use App\Documentation\Contracts\ScannerInterface;
use App\Documentation\DocEntity;
use App\Documentation\DocId;
use App\Documentation\ValueObjects\HttpEndpointMeta;
use Illuminate\Support\Facades\Route;

final class HttpEndpointScanner implements ScannerInterface
{
    /**
     * @return array<DocEntity>
     */
    public function scan(): array
    {
        $entities = [];
        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            // Пропускаем системные маршруты
            if ($this->shouldSkipRoute($route)) {
                continue;
            }

            $entity = $this->scanRoute($route);
            if ($entity !== null) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    private function shouldSkipRoute(\Illuminate\Routing\Route $route): bool
    {
        $uri = $route->uri();

        // Пропускаем системные маршруты
        if (in_array($uri, ['up', 'sanctum/csrf-cookie'])) {
            return true;
        }

        // Пропускаем маршруты без имени (обычно это служебные)
        if (empty($route->getName())) {
            // Но оставляем API маршруты
            if (! str_starts_with($uri, 'api/')) {
                return true;
            }
        }

        return false;
    }

    private function scanRoute(\Illuminate\Routing\Route $route): ?DocEntity
    {
        $methods = $route->methods();
        if (empty($methods)) {
            return null;
        }

        // Берем первый метод (обычно один)
        $method = $methods[0];
        $uri = '/'.$route->uri();

        // Извлекаем метаданные
        $meta = $this->extractMeta($route, $method, $uri);

        // Генерируем имя
        $name = $route->getName() ?: $this->generateName($method, $uri);

        // Генерируем summary
        $summary = $this->generateSummary($method, $uri, $meta->group);

        return new DocEntity(
            id: DocId::forHttpEndpoint($method, $uri),
            type: 'http_endpoint',
            name: $name,
            path: $this->getControllerPath($route),
            summary: $summary,
            details: null,
            meta: $meta->toArray(),
            related: [],
            tags: $this->extractTags($uri, $meta->group),
        );
    }

    private function extractMeta(\Illuminate\Routing\Route $route, string $method, string $uri): HttpEndpointMeta
    {
        // Определяем group из middleware
        $group = $this->extractGroup($route);

        // Определяем auth из middleware
        $auth = $this->extractAuth($route);

        // Извлекаем параметры из URI
        $parameters = $this->extractParameters($uri);

        // Извлекаем responses (пока пусто, можно расширить через Scribe)
        $responses = [];

        return new HttpEndpointMeta(
            method: $method,
            uri: $uri,
            group: $group,
            auth: $auth,
            parameters: $parameters,
            responses: $responses,
        );
    }

    private function extractGroup(\Illuminate\Routing\Route $route): ?string
    {
        $middleware = $route->gatherMiddleware();

        if (in_array('web', $middleware)) {
            return 'web';
        }
        if (in_array('api', $middleware)) {
            return 'api';
        }

        return null;
    }

    private function extractAuth(\Illuminate\Routing\Route $route): ?string
    {
        $middleware = $route->gatherMiddleware();

        if (in_array('jwt.auth', $middleware)) {
            return 'jwt';
        }
        if (in_array('auth', $middleware) || in_array('auth:sanctum', $middleware)) {
            return 'auth';
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function extractParameters(string $uri): array
    {
        $parameters = [];

        // Ищем параметры в фигурных скобках
        if (preg_match_all('/\{(\w+)(?::([^}]+))?\}/', $uri, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $paramName = $match[1];
                $paramConstraint = $match[2] ?? null;

                $parameters[$paramName] = [
                    'required' => true,
                    'type' => 'string',
                    'constraint' => $paramConstraint,
                ];
            }
        }

        return $parameters;
    }

    private function generateName(string $method, string $uri): string
    {
        $parts = explode('/', trim($uri, '/'));
        $lastPart = end($parts);

        return strtolower($method).'.'.str_replace(['{', '}'], '', $lastPart);
    }

    private function generateSummary(string $method, string $uri, ?string $group): string
    {
        $groupPart = $group ? " ({$group})" : '';
        return "{$method} {$uri}{$groupPart}";
    }

    /**
     * @return array<string>
     */
    private function extractTags(string $uri, ?string $group): array
    {
        $tags = [];

        if ($group) {
            $tags[] = $group;
        }

        // Теги из URI
        if (str_starts_with($uri, '/api/')) {
            $tags[] = 'api';
            if (str_contains($uri, '/admin/')) {
                $tags[] = 'admin';
            }
        }

        // Теги из сегментов URI
        $segments = explode('/', trim($uri, '/'));
        foreach ($segments as $segment) {
            if (! empty($segment) && ! str_starts_with($segment, '{')) {
                $tags[] = $segment;
            }
        }

        return array_unique($tags);
    }

    private function getControllerPath(\Illuminate\Routing\Route $route): string
    {
        $action = $route->getAction();

        if (isset($action['controller'])) {
            $controller = $action['controller'];
            if (is_string($controller) && str_contains($controller, '@')) {
                [$class, $method] = explode('@', $controller);
                try {
                    $reflection = new \ReflectionClass($class);
                    $relativePath = str_replace(base_path().'/', '', $reflection->getFileName());
                    return $relativePath;
                } catch (\Throwable) {
                    // Игнорируем ошибки
                }
            }
        }

        return 'routes';
    }
}

