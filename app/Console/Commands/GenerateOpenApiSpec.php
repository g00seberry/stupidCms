<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class GenerateOpenApiSpec extends Command
{
    protected $signature = 'docs:openapi {--format=json : Формат экспорта (json|yaml)}';
    protected $description = 'Generate OpenAPI specification from registered API routes';

    /** @var array<string, array<string, mixed>> Кеш распарсенных Resource-классов */
    private array $resourceSchemaCache = [];

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
                'schemas' => array_merge(
                    [
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
                    $this->resourceSchemaCache
                ),
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

        // Пытаемся извлечь Resource из контроллера
        $resourceClass = $this->resolveResourceClass($route);
        $responses = $this->buildResponses($route, $httpMethod, $resourceClass);

        $operation = [
            'tags' => [$tag],
            'operationId' => (string) $operationId,
            'summary' => $this->buildSummary($routeDefinition, $httpMethod),
            'parameters' => $this->buildPathParameters($route),
            'responses' => $responses,
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
    /**
     * Строит responses с учетом Resource класса
     *
     * @return array<string, mixed>
     */
    private function buildResponses(Route $route, string $httpMethod, ?string $resourceClass): array
    {
        $responses = [];

        // Для DELETE обычно 204
        if ($httpMethod === 'delete') {
            $responses['204'] = [
                'description' => 'Ресурс успешно удалён',
            ];
        } else {
            // Строим 200 ответ
            if ($resourceClass !== null) {
                $schemaName = class_basename($resourceClass);
                $this->ensureResourceSchema($resourceClass, $schemaName);

                $responses['200'] = [
                    'description' => 'Успешный ответ',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => "#/components/schemas/{$schemaName}"],
                        ],
                    ],
                ];
            } else {
                // Дефолтный ответ если Resource не найден
                $responses['200'] = [
                    'description' => 'Успешный ответ',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => true,
                            ],
                        ],
                    ],
                ];
            }
        }

        // Стандартные коды ошибок
        $responses['401'] = [
            'description' => 'Неавторизовано',
            'content' => [
                'application/problem+json' => [
                    'schema' => ['$ref' => '#/components/schemas/ProblemDetails'],
                ],
            ],
        ];
        
        $responses['403'] = [
            'description' => 'Доступ запрещён',
            'content' => [
                'application/problem+json' => [
                    'schema' => ['$ref' => '#/components/schemas/ProblemDetails'],
                ],
            ],
        ];
        
        $responses['422'] = [
            'description' => 'Ошибка валидации',
            'content' => [
                'application/problem+json' => [
                    'schema' => ['$ref' => '#/components/schemas/ProblemDetails'],
                ],
            ],
        ];
        
        $responses['500'] = [
            'description' => 'Внутренняя ошибка сервера',
            'content' => [
                'application/problem+json' => [
                    'schema' => ['$ref' => '#/components/schemas/ProblemDetails'],
                ],
            ],
        ];

        return $responses;
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

    /**
     * Извлекает Resource класс из контроллера
     */
    private function resolveResourceClass(Route $route): ?string
    {
        $action = $route->getActionName();

        if (! is_string($action) || ! Str::contains($action, '@')) {
            return null;
        }

        [$class, $method] = Str::parseCallback($action);

        if (! $class || ! $method || ! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($class, $method);
        $returnType = $reflection->getReturnType();

        // Проверяем return type
        if ($returnType instanceof ReflectionNamedType) {
            $returnClass = $returnType->getName();

            if (is_subclass_of($returnClass, JsonResource::class) || is_subclass_of($returnClass, ResourceCollection::class)) {
                return $returnClass;
            }
        }

        // Парсим код метода для поиска Resource
        try {
            $source = file_get_contents($reflection->getFileName());
            $methodSource = $this->extractMethodSource($source, $reflection);

            // Паттерн: return new SomeResource(...) или (new SomeResource(...))->...
            if (preg_match('/return\s+\(?new\s+([A-Z]\w+(?:Resource|Collection))\s*\(/s', $methodSource, $matches)) {
                $resourceClass = $this->resolveFullClassName($matches[1], $class);
                if ($resourceClass && (is_subclass_of($resourceClass, JsonResource::class) || is_subclass_of($resourceClass, ResourceCollection::class))) {
                    return $resourceClass;
                }
            }

            // Паттерн: return SomeResource::collection(...) или SomeResource::make(...)
            if (preg_match('/return\s+([A-Z]\w+Resource)::(collection|make)\s*\(/s', $methodSource, $matches)) {
                $resourceClass = $this->resolveFullClassName($matches[1], $class);
                if ($resourceClass && is_subclass_of($resourceClass, JsonResource::class)) {
                    return $resourceClass;
                }
            }

            // Паттерн: return (new SomeResource(...))->toResponse(...) или ->response()->...
            if (preg_match('/return\s+\(new\s+([A-Z]\w+(?:Resource|Collection))\s*\([^)]*\)\s*\)->/s', $methodSource, $matches)) {
                $resourceClass = $this->resolveFullClassName($matches[1], $class);
                if ($resourceClass && (is_subclass_of($resourceClass, JsonResource::class) || is_subclass_of($resourceClass, ResourceCollection::class))) {
                    return $resourceClass;
                }
            }

            // Паттерн: $var = new SomeResource(...); ... return $var->...
            if (preg_match('/\$\w+\s*=\s*new\s+([A-Z]\w+(?:Resource|Collection))\s*\(/s', $methodSource, $matches)) {
                $resourceClass = $this->resolveFullClassName($matches[1], $class);
                if ($resourceClass && (is_subclass_of($resourceClass, JsonResource::class) || is_subclass_of($resourceClass, ResourceCollection::class))) {
                    return $resourceClass;
                }
            }

            // Паттерн: $var = SomeResource::collection(...); ... return $var
            if (preg_match('/\$\w+\s*=\s*([A-Z]\w+Resource)::collection\s*\(/s', $methodSource, $matches)) {
                $resourceClass = $this->resolveFullClassName($matches[1], $class);
                if ($resourceClass && is_subclass_of($resourceClass, JsonResource::class)) {
                    return $resourceClass;
                }
            }

            // Паттерн: $response = SomeResource::collection(...)->response();
            if (preg_match('/\$\w+\s*=\s*([A-Z]\w+Resource)::collection\([^)]+\)->response\(\)/s', $methodSource, $matches)) {
                $resourceClass = $this->resolveFullClassName($matches[1], $class);
                if ($resourceClass && is_subclass_of($resourceClass, JsonResource::class)) {
                    return $resourceClass;
                }
            }

            // Fallback: ищем любое упоминание Resource/Collection в теле метода
            if (preg_match('/(?:new|::)\s*([A-Z]\w+(?:Resource|Collection))(?:\s*\(|::)/s', $methodSource, $matches)) {
                $resourceClass = $this->resolveFullClassName($matches[1], $class);
                if ($resourceClass && (is_subclass_of($resourceClass, JsonResource::class) || is_subclass_of($resourceClass, ResourceCollection::class))) {
                    return $resourceClass;
                }
            }
        } catch (\Throwable) {
            // Игнорируем ошибки парсинга
        }

        return null;
    }

    /**
     * Извлекает метод из исходника файла
     */
    private function extractMethodSource(string $fileSource, ReflectionMethod $method): string
    {
        $lines = explode("\n", $fileSource);
        $start = $method->getStartLine() - 1;
        $end = $method->getEndLine();

        return implode("\n", array_slice($lines, $start, $end - $start));
    }

    /**
     * Разрешает полное имя класса из короткого имени
     */
    private function resolveFullClassName(string $shortName, string $contextClass): ?string
    {
        $reflection = new ReflectionClass($contextClass);
        $namespace = $reflection->getNamespaceName();

        // Пробуем в том же namespace
        $candidate = $namespace . '\\' . $shortName;
        if (class_exists($candidate)) {
            return $candidate;
        }

        // Пробуем в App\Http\Resources
        $candidate = 'App\\Http\\Resources\\' . $shortName;
        if (class_exists($candidate)) {
            return $candidate;
        }

        // Пробуем в App\Http\Resources\Admin
        $candidate = 'App\\Http\\Resources\\Admin\\' . $shortName;
        if (class_exists($candidate)) {
            return $candidate;
        }

        return null;
    }

    /**
     * Гарантирует наличие схемы Resource в кеше
     */
    private function ensureResourceSchema(string $resourceClass, string $schemaName): void
    {
        if (isset($this->resourceSchemaCache[$schemaName])) {
            return;
        }

        try {
            $reflection = new ReflectionClass($resourceClass);
            
            // Специальная обработка для ResourceCollection
            if ($reflection->isSubclassOf(ResourceCollection::class)) {
                $schema = $this->parseResourceCollection($reflection, $resourceClass);
                if ($schema !== null) {
                    $this->resourceSchemaCache[$schemaName] = $schema;
                    return;
                }
            }
            
            if (! $reflection->hasMethod('toArray')) {
                throw new \Exception('No toArray method');
            }

            $toArrayMethod = $reflection->getMethod('toArray');
            $source = file_get_contents($toArrayMethod->getFileName());
            $methodSource = $this->extractMethodSource($source, $toArrayMethod);

            $schema = $this->parseResourceToArray($methodSource, $resourceClass);

            if ($schema !== null) {
                $this->resourceSchemaCache[$schemaName] = $schema;
                return;
            }
        } catch (\Throwable) {
            // Fallback
        }

        // Если не смогли распарсить — дефолтная схема
        $this->resourceSchemaCache[$schemaName] = [
            'type' => 'object',
            'additionalProperties' => true,
            'description' => 'Схема из ' . class_basename($resourceClass),
        ];
    }

    /**
     * Парсит ResourceCollection используя $collects property
     *
     * @return array<string, mixed>|null
     */
    private function parseResourceCollection(ReflectionClass $reflection, string $resourceClass): ?array
    {
        // Ищем public $collects property
        if ($reflection->hasProperty('collects')) {
            $collectsProperty = $reflection->getProperty('collects');
            $collectsProperty->setAccessible(true);
            
            $defaultValue = $collectsProperty->getDefaultValue();
            
            if ($defaultValue && is_string($defaultValue) && class_exists($defaultValue)) {
                // Гарантируем что схема для вложенного Resource есть
                $itemSchemaName = class_basename($defaultValue);
                $this->ensureResourceSchema($defaultValue, $itemSchemaName);
                
                return [
                    'type' => 'object',
                    'properties' => [
                        'data' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => "#/components/schemas/{$itemSchemaName}",
                            ],
                        ],
                        'links' => $this->paginationLinksSchema(),
                        'meta' => $this->paginationMetaSchema(),
                    ],
                    'required' => ['data', 'links', 'meta'],
                    'description' => 'Paginated collection из ' . class_basename($resourceClass),
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function paginationLinksSchema(): array
    {
        return [
            'type' => 'object',
            'description' => 'Pagination links',
            'properties' => [
                'first' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'nullable' => true,
                ],
                'last' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'nullable' => true,
                ],
                'prev' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'nullable' => true,
                ],
                'next' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'nullable' => true,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paginationMetaSchema(): array
    {
        return [
            'type' => 'object',
            'description' => 'Pagination metadata',
            'properties' => [
                'current_page' => [
                    'type' => 'integer',
                    'format' => 'int32',
                ],
                'from' => [
                    'type' => 'integer',
                    'format' => 'int32',
                    'nullable' => true,
                ],
                'last_page' => [
                    'type' => 'integer',
                    'format' => 'int32',
                ],
                'path' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'format' => 'int32',
                ],
                'to' => [
                    'type' => 'integer',
                    'format' => 'int32',
                    'nullable' => true,
                ],
                'total' => [
                    'type' => 'integer',
                    'format' => 'int32',
                ],
                'links' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'url' => [
                                'type' => 'string',
                                'format' => 'uri',
                                'nullable' => true,
                            ],
                            'label' => [
                                'type' => 'string',
                            ],
                            'page' => [
                                'type' => 'integer',
                                'format' => 'int32',
                                'nullable' => true,
                            ],
                            'active' => [
                                'type' => 'boolean',
                            ],
                        ],
                        'required' => ['label', 'active'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['current_page', 'last_page', 'path', 'per_page', 'total', 'links'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Парсит метод toArray из Resource класса
     *
     * @return array<string, mixed>|null
     */
    private function parseResourceToArray(string $methodSource, string $resourceClass): ?array
    {
        // Ищем return [ ... ];
        if (! preg_match('/return\s+\[(.*)\];/s', $methodSource, $matches)) {
            return null;
        }

        $arrayContent = $matches[1];
        $properties = [];
        $required = [];
        
        // Разбиваем по строкам
        $lines = preg_split('/\r?\n/', $arrayContent);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === '' || str_starts_with($line, '//') || str_starts_with($line, '*')) {
                continue;
            }

            // Паттерн: 'key' => выражение
            if (preg_match("/^['\"](\w+)['\"]\s*=>\s*(.+?)(?:,\s*|\s*)$/s", $line, $keyMatch)) {
                $key = $keyMatch[1];
                $expression = isset($keyMatch[2]) ? trim($keyMatch[2], ', ') : '';
                
                $property = $this->inferTypeFromExpression($expression, $line);
                
                // Если нет nullable и нет when() — считаем required
                if (! isset($property['nullable']) && ! isset($property['oneOf']) && ! str_contains($line, '$this->when')) {
                    $required[] = $key;
                }
                
                $properties[$key] = $property;
            }
        }

        if ($properties === []) {
            return null;
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'description' => 'Схема из ' . class_basename($resourceClass),
        ];

        if ($required !== []) {
            $schema['required'] = array_values(array_unique($required));
        }

        return $schema;
    }

    /**
     * Определяет тип поля из выражения в Resource
     *
     * @return array<string, mixed>
     */
    private function inferTypeFromExpression(string $expression, string $fullLine): array
    {
        $expr = trim($expression);

        // Nullable: ->field?->... или ?? 
        $nullable = str_contains($expr, '?->') || str_contains($expr, '??');

        // ID поля
        if (preg_match('/\bid\b|_id\b/i', $fullLine) || str_contains($expr, '->id')) {
            $schema = ['type' => 'integer', 'format' => 'int64'];
            if ($nullable) {
                $schema['nullable'] = true;
            }
            return $schema;
        }

        // Email
        if (preg_match('/\bemail\b/i', $fullLine)) {
            $schema = ['type' => 'string', 'format' => 'email'];
            if ($nullable) {
                $schema['nullable'] = true;
            }
            return $schema;
        }

        // Timestamps
        if (preg_match('/\b(created_at|updated_at|published_at|deleted_at|scheduled_at)\b/i', $fullLine)) {
            $schema = ['type' => 'string', 'format' => 'date-time'];
            if ($nullable) {
                $schema['nullable'] = true;
            }
            return $schema;
        }

        // Boolean (is_, has_, enabled, published или сравнения)
        if (preg_match('/\b(is_|has_|enabled|published)\w*/i', $fullLine) || str_contains($expr, '===') || str_contains($expr, '!==')) {
            return ['type' => 'boolean'];
        }

        // Arrays/Collections: ->map(), ->pluck(), ->toArray()
        if (str_contains($expr, '->map(') || str_contains($expr, '->pluck(') || str_contains($expr, '->toArray()')) {
            return [
                'type' => 'array',
                'items' => ['type' => 'object'],
            ];
        }

        // Objects (new \stdClass, transformJson)
        if (str_contains($expr, 'new \\stdClass') || str_contains($expr, 'transformJson')) {
            return ['type' => 'object'];
        }

        // when() — может быть null
        if (str_contains($expr, '$this->when(')) {
            return [
                'oneOf' => [
                    ['type' => 'object'],
                    ['type' => 'null'],
                ],
            ];
        }

        // URL/slug/path
        if (preg_match('/\b(url|slug|path|link)\b/i', $fullLine)) {
            $schema = ['type' => 'string'];
            if ($nullable) {
                $schema['nullable'] = true;
            }
            return $schema;
        }

        // Дефолт: string
        $schema = ['type' => 'string'];
        if ($nullable) {
            $schema['nullable'] = true;
        }
        return $schema;
    }
}


