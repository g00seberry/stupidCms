<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class GenerateOpenApiSpec extends Command
{
    protected $signature = 'docs:openapi {--format=json : Формат экспорта (json|yaml)}';
    protected $description = 'Generate OpenAPI specification from registered API routes';

    public function handle(): int
    {
        $routes = $this->collectApiRoutes();

        if ($routes->isEmpty()) {
            $this->warn('No API routes found. Skipping OpenAPI generation.');

            return self::SUCCESS;
        }

        $this->info('Generating OpenAPI specification...');

        $spec = $this->buildSpecification($routes);
        $this->exportSpecification($spec);

        $this->info('✓ Generated: storage/api-docs/openapi.json');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{uri:string,methods:array<int,string>,name:?string,category:string,resource:string,route:\Illuminate\Routing\Route}>
     */
    private function collectApiRoutes(): Collection
    {
        /** @var Collection<int, Route> $routes */
        $routes = collect(RouteFacade::getRoutes());

        return $routes
            ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
            ->map(function (Route $route): array {
                $methods = collect($route->methods())
                    ->reject(fn (string $method): bool => $method === 'HEAD')
                    ->unique()
                    ->values()
                    ->all();

                if ($methods === []) {
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
                    'route' => $route,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param Collection<int, array{uri:string,methods:array<int,string>,name:?string,category:string,resource:string,route:\Illuminate\Routing\Route}> $routes
     * @return array<string, mixed>
     */
    private function buildSpecification(Collection $routes): array
    {
        $grouped = $routes
            ->groupBy('uri')
            ->sortKeys();

        $paths = [];

        foreach ($grouped as $uri => $routeGroup) {
            $paths[$uri] = [];

            foreach ($routeGroup as $routeDefinition) {
                foreach ($routeDefinition['methods'] as $method) {
                    $operation = $this->buildOperation(
                        strtolower($method),
                        $routeDefinition
                    );

                    $paths[$uri][strtolower($method)] = $operation;
                }
            }
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'stupidCms API',
                'version' => '1.0.0',
                'description' => <<<MD
Автоматически сгенерированное описание всех маршрутов `/api/*`.

> docs:gap — уточнить схемы запросов и ответов, добавить примеры.
MD,
            ],
            'servers' => [
                [
                    'url' => config('app.url') . '/api',
                    'description' => 'Application base URL',
                ],
            ],
            'tags' => [
                ['name' => 'Public', 'description' => 'Публичные точки доступа'],
                ['name' => 'Admin', 'description' => 'Административное API'],
                ['name' => 'Auth', 'description' => 'Аутентификация и управление сессией'],
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'Передайте access-токен в заголовке `Authorization: Bearer <token>`.',
                    ],
                ],
                'schemas' => [
                    'ProblemDetails' => [
                        'type' => 'object',
                        'description' => 'RFC7807 Problem Details',
                        'required' => ['type', 'title', 'status'],
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'format' => 'uri',
                            ],
                            'title' => [
                                'type' => 'string',
                            ],
                            'status' => [
                                'type' => 'integer',
                                'format' => 'int32',
                            ],
                            'detail' => [
                                'type' => 'string',
                                'nullable' => true,
                            ],
                            'instance' => [
                                'type' => 'string',
                                'nullable' => true,
                            ],
                            'errors' => [
                                'type' => 'object',
                                'additionalProperties' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
        ];
    }

    /**
     * @param array{uri:string,methods:array<int,string>,name:?string,category:string,resource:string,route:\Illuminate\Routing\Route} $routeDefinition
     * @return array<string, mixed>
     */
    private function buildOperation(string $httpMethod, array $routeDefinition): array
    {
        /** @var Route $route */
        $route = $routeDefinition['route'];
        $tag = match ($routeDefinition['category']) {
            'admin' => 'Admin',
            'auth' => 'Auth',
            default => 'Public',
        };

        $operationId = $routeDefinition['name']
            ? Str::of($routeDefinition['name'])->replace('.', '_')->camel()
            : Str::of($routeDefinition['resource'])->slug('_') . '_' . $httpMethod;

        $operation = [
            'tags' => [$tag],
            'operationId' => (string) $operationId,
            'summary' => $this->buildSummary($routeDefinition, $httpMethod),
            'parameters' => $this->buildPathParameters($route),
            'responses' => $this->defaultResponses(),
        ];

        if ($tag === 'Admin' || ($tag === 'Auth' && $this->shouldSecureAuthRoute($routeDefinition['uri']))) {
            $operation['security'] = [
                ['bearerAuth' => []],
            ];
        } elseif ($tag === 'Auth') {
            $operation['security'] = [];
        } else {
            $operation['security'] = [];
        }

        if (in_array($httpMethod, ['post', 'put', 'patch'], true)) {
            $operation['requestBody'] = $this->defaultRequestBody();
        }

        return $operation;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultResponses(): array
    {
        return [
            '200' => [
                'description' => 'Успешный ответ',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                            'description' => 'docs:gap — добавить детальное описание схемы.',
                        ],
                    ],
                ],
            ],
            '401' => [
                'description' => 'Неавторизовано',
                'content' => [
                    'application/problem+json' => [
                        'schema' => ['$ref' => '#/components/schemas/ProblemDetails'],
                    ],
                ],
            ],
            '403' => [
                'description' => 'Доступ запрещён',
                'content' => [
                    'application/problem+json' => [
                        'schema' => ['$ref' => '#/components/schemas/ProblemDetails'],
                    ],
                ],
            ],
            '422' => [
                'description' => 'Ошибка валидации',
                'content' => [
                    'application/problem+json' => [
                        'schema' => ['$ref' => '#/components/schemas/ProblemDetails'],
                    ],
                ],
            ],
            '500' => [
                'description' => 'Внутренняя ошибка сервера',
                'content' => [
                    'application/problem+json' => [
                        'schema' => ['$ref' => '#/components/schemas/ProblemDetails'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultRequestBody(): array
    {
        return [
            'required' => false,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                        'description' => 'docs:gap — описать структуру запроса.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPathParameters(Route $route): array
    {
        return collect($route->parameterNames())
            ->unique()
            ->map(fn (string $parameter): array => [
                'name' => $parameter,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param array{uri:string,methods:array<int,string>,name:?string,category:string,resource:string} $routeDefinition
     */
    private function buildSummary(array $routeDefinition, string $httpMethod): string
    {
        if ($routeDefinition['name']) {
            return (string) Str::of($routeDefinition['name'])
                ->replace('.', ' ')
                ->replace('_', ' ')
                ->headline();
        }

        $resource = $routeDefinition['resource'] === 'root'
            ? 'Root'
            : Str::headline(Str::slug($routeDefinition['resource'], ' '));

        return Str::upper($httpMethod) . ' ' . $resource;
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

        array_shift($segments);

        if ($category === 'admin') {
            if (! empty($segments) && preg_match('/^v\\d+$/', $segments[0])) {
                array_shift($segments);
            }

            if (! empty($segments) && $segments[0] === 'admin') {
                array_shift($segments);
            }
        } elseif ($category === 'auth') {
            if (! empty($segments) && preg_match('/^v\\d+$/', $segments[0])) {
                array_shift($segments);
            }

            if (! empty($segments) && $segments[0] === 'auth') {
                array_shift($segments);
            }
        } else {
            if (! empty($segments) && preg_match('/^v\\d+$/', $segments[0])) {
                array_shift($segments);
            }
        }

        return $segments[0] ?? 'root';
    }

    private function shouldSecureAuthRoute(string $uri): bool
    {
        return ! preg_match('#/auth/(login|csrf)$#', $uri);
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function exportSpecification(array $spec): void
    {
        $format = strtolower((string) $this->option('format'));

        $directory = storage_path('api-docs');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Failed to create directory: %s', $directory));
        }

        $json = json_encode($spec, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'openapi.json', $json);

        if ($format === 'yaml') {
            $yamlPath = $directory . DIRECTORY_SEPARATOR . 'openapi.yaml';
            $yaml = $this->toYaml($spec);
            file_put_contents($yamlPath, $yaml);
            $this->info('✓ Generated: storage/api-docs/openapi.yaml');
        }
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function toYaml(array $spec): string
    {
        if (! class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            return $this->arrayToYaml($spec);
        }

        /** @var class-string $yamlClass */
        $yamlClass = \Symfony\Component\Yaml\Yaml::class;

        return $yamlClass::dump($spec, 10, 2, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    /**
     * @param array<string, mixed> $array
     */
    private function arrayToYaml(array $array, int $indentLevel = 0): string
    {
        $indent = str_repeat('  ', $indentLevel);
        $yaml = '';

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $isAssoc = array_keys($value) !== range(0, count($value) - 1);
                $yaml .= sprintf("%s%s:\n", $indent, $key);

                if ($isAssoc) {
                    $yaml .= $this->arrayToYaml($value, $indentLevel + 1);
                } else {
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= sprintf("%s- \n", str_repeat('  ', $indentLevel + 1));
                            $yaml .= $this->arrayToYaml($item, $indentLevel + 2);
                        } else {
                            $yaml .= sprintf("%s- %s\n", str_repeat('  ', $indentLevel + 1), $item);
                        }
                    }
                }

                continue;
            }

            $yaml .= sprintf("%s%s: %s\n", $indent, $key, $this->scalarToYaml($value));
        }

        return $yaml;
    }

    private function scalarToYaml(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            default => (string) $value,
        };
    }
}


