<?php

declare(strict_types=1);

namespace App\Documentation\Scanners;

use App\Documentation\Contracts\ScannerInterface;
use App\Documentation\DocEntity;
use App\Documentation\DocId;
use App\Documentation\ValueObjects\BladeViewMeta;
use Illuminate\Support\Facades\File;

final class BladeViewScanner implements ScannerInterface
{
    private const VIEWS_PATH = 'resources/views';

    /**
     * @return array<DocEntity>
     */
    public function scan(): array
    {
        $viewsPath = base_path(self::VIEWS_PATH);
        if (! File::exists($viewsPath)) {
            return [];
        }

        $entities = [];
        $files = File::allFiles($viewsPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $entity = $this->scanBladeView($file->getPathname());
            if ($entity !== null) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    private function scanBladeView(string $filePath): ?DocEntity
    {
        $content = File::get($filePath);
        if ($content === false) {
            return null;
        }

        $relativePath = str_replace(base_path().'/', '', $filePath);
        $viewName = str_replace('resources/views/', '', $relativePath);
        $name = basename($viewName, '.blade.php');

        // Извлекаем метаданные
        $meta = $this->extractMeta($content, $viewName);

        // Генерируем summary
        $summary = $this->generateSummary($viewName, $meta->role);

        return new DocEntity(
            id: DocId::forBladeView($viewName),
            type: 'blade_view',
            name: $name,
            path: $relativePath,
            summary: $summary,
            details: null,
            meta: $meta->toArray(),
            related: [],
            tags: [$meta->role],
        );
    }

    private function extractMeta(string $content, string $viewName): BladeViewMeta
    {
        // Определяем role по пути
        $role = $this->determineRole($viewName);

        // Извлекаем переменные
        $variables = $this->extractVariables($content);

        // Извлекаем @extends
        $extends = $this->extractExtends($content);

        // Извлекаем @include и @includeIf
        $includes = $this->extractIncludes($content);

        return new BladeViewMeta(
            role: $role,
            variables: $variables,
            extends: $extends,
            includes: $includes,
        );
    }

    private function determineRole(string $viewName): string
    {
        if (str_starts_with($viewName, 'layouts/')) {
            return 'layout';
        }
        if (str_starts_with($viewName, 'partials/')) {
            return 'partial';
        }
        if (str_starts_with($viewName, 'errors/')) {
            return 'error';
        }
        if (str_contains($viewName, '/')) {
            return 'page';
        }

        return 'entry';
    }

    /**
     * @return array<string>
     */
    private function extractVariables(string $content): array
    {
        $variables = [];

        // Ищем $variable в шаблоне
        if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $content, $matches)) {
            $variables = array_unique($matches[1]);
        }

        return array_values($variables);
    }

    private function extractExtends(string $content): ?string
    {
        if (preg_match('/@extends\([\'"]([^\'"]+)[\'"]\)/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array<string>
     */
    private function extractIncludes(string $content): array
    {
        $includes = [];

        // @include и @includeIf
        if (preg_match_all('/@include(?:If)?\([\'"]([^\'"]+)[\'"]\)/', $content, $matches)) {
            $includes = array_unique($matches[1]);
        }

        return array_values($includes);
    }

    private function generateSummary(string $viewName, string $role): string
    {
        $name = basename($viewName, '.blade.php');
        
        return match ($role) {
            'layout' => "Layout template: {$name}",
            'partial' => "Partial template: {$name}",
            'error' => "Error page template: {$name}",
            'page' => "Page template: {$viewName}",
            default => "Entry template: {$name}",
        };
    }
}

