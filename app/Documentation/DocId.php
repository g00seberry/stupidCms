<?php

declare(strict_types=1);

namespace App\Documentation;

final class DocId
{
    public static function forModel(string $fqcn): string
    {
        return "model:{$fqcn}";
    }

    public static function forDomainService(string $namespacePath): string
    {
        // Убираем App\Domain\ если есть
        $path = str_replace('App\\Domain\\', '', $namespacePath);
        $path = str_replace('\\', '/', $path);
        return "domain_service:{$path}";
    }

    public static function forBladeView(string $relativePath): string
    {
        // Нормализуем путь: убираем resources/views/, заменяем \ на /
        $path = str_replace('resources/views/', '', $relativePath);
        $path = str_replace('\\', '/', $path);
        return "blade_view:{$path}";
    }

    public static function forConfigArea(string $configFile): string
    {
        // Убираем .php и config/
        $name = str_replace(['config/', '.php'], '', $configFile);
        return "config_area:{$name}";
    }

    public static function forConcept(string $domain, string $name): string
    {
        return "concept:{$domain}:{$name}";
    }

    public static function forHttpEndpoint(string $method, string $uri): string
    {
        return "http_endpoint:{$method}:{$uri}";
    }

    /**
     * Разбирает ID на тип и значение.
     *
     * @return array{type: string, value: string}|null
     */
    public static function parse(string $id): ?array
    {
        if (! preg_match('/^([^:]+):(.+)$/', $id, $matches)) {
            return null;
        }

        return [
            'type' => $matches[1],
            'value' => $matches[2],
        ];
    }
}

