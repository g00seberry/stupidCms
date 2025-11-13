<?php

declare(strict_types=1);

namespace App\Documentation\Generators;

use App\Documentation\Contracts\GeneratorInterface;
use App\Documentation\DocEntity;
use Illuminate\Support\Facades\File;

final class MarkdownGenerator implements GeneratorInterface
{
    private string $outputDir;

    public function __construct(?string $outputDir = null)
    {
        $this->outputDir = $outputDir ?? base_path(config('docs.output.markdown_dir', 'docs/generated'));
    }

    /**
     * @param array<DocEntity> $entities
     */
    public function generate(array $entities): void
    {
        // Создаем директорию, если не существует
        File::ensureDirectoryExists($this->outputDir);

        // Группируем по типам
        $byType = $this->groupByType($entities);

        // Генерируем файлы для каждого типа
        foreach ($byType as $type => $typeEntities) {
            $this->generateTypeFile($type, $typeEntities);
        }
    }

    /**
     * @param array<DocEntity> $entities
     * @return array<string, array<DocEntity>>
     */
    private function groupByType(array $entities): array
    {
        $grouped = [];

        foreach ($entities as $entity) {
            $grouped[$entity->type][] = $entity;
        }

        // Сортируем сущности внутри каждого типа по имени
        foreach ($grouped as &$typeEntities) {
            usort($typeEntities, fn($a, $b) => strcmp($a->name, $b->name));
        }

        return $grouped;
    }

    /**
     * @param array<DocEntity> $entities
     */
    private function generateTypeFile(string $type, array $entities): void
    {
        $fileName = $this->getFileNameForType($type);
        $filePath = $this->outputDir.'/'.$fileName;

        $content = $this->generateMarkdown($type, $entities);

        File::put($filePath, $content);
    }

    private function getFileNameForType(string $type): string
    {
        return match ($type) {
            'model' => 'models.md',
            'domain_service' => 'domain-services.md',
            'blade_view' => 'blade-views.md',
            'config_area' => 'config-areas.md',
            'concept' => 'concepts.md',
            'http_endpoint' => 'http-endpoints.md',
            default => "{$type}.md",
        };
    }

    /**
     * @param array<DocEntity> $entities
     */
    private function generateMarkdown(string $type, array $entities): string
    {
        $title = $this->getTitleForType($type);
        $lines = ["# {$title}\n"];

        foreach ($entities as $entity) {
            $lines[] = $this->generateEntityMarkdown($entity);
            $lines[] = "\n---\n";
        }

        return implode("\n", $lines);
    }

    private function getTitleForType(string $type): string
    {
        return match ($type) {
            'model' => 'Models',
            'domain_service' => 'Domain Services',
            'blade_view' => 'Blade Views',
            'config_area' => 'Config Areas',
            'concept' => 'Concepts',
            'http_endpoint' => 'HTTP Endpoints',
            default => ucfirst($type),
        };
    }

    private function generateEntityMarkdown(DocEntity $entity): string
    {
        $lines = [];

        // Нормализуем путь (убираем абсолютный путь)
        $basePath = str_replace('\\', '/', base_path());
        $path = str_replace('\\', '/', $entity->path);
        $path = str_replace($basePath.'/', '', $path);
        $path = str_replace($basePath, '', $path);

        // Заголовок
        $lines[] = "## {$entity->name}";
        $lines[] = "**ID:** `{$entity->id}`";
        $lines[] = "**Path:** `{$path}`";
        $lines[] = "";

        // Summary
        $lines[] = $entity->summary;
        $lines[] = "";

        // Details
        if ($entity->details) {
            $lines[] = "### Details";
            $lines[] = $entity->details;
            $lines[] = "";
        }

        // Meta
        if (! empty($entity->meta)) {
            $lines[] = "### Meta";
            $lines[] = $this->formatMeta($entity->type, $entity->meta);
            $lines[] = "";
        }

        // Related
        if (! empty($entity->related)) {
            $lines[] = "### Related";
            foreach ($entity->related as $relatedId) {
                $lines[] = "- `{$relatedId}`";
            }
            $lines[] = "";
        }

        // Tags
        if (! empty($entity->tags)) {
            $lines[] = "### Tags";
            $tags = array_map(fn($tag) => "`{$tag}`", $entity->tags);
            $lines[] = implode(', ', $tags);
            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function formatMeta(string $type, array $meta): string
    {
        $lines = [];

        match ($type) {
            'model' => $lines = $this->formatModelMeta($meta),
            'domain_service' => $lines = $this->formatDomainServiceMeta($meta),
            'blade_view' => $lines = $this->formatBladeViewMeta($meta),
            'config_area' => $lines = $this->formatConfigAreaMeta($meta),
            'http_endpoint' => $lines = $this->formatHttpEndpointMeta($meta),
            default => $lines = $this->formatGenericMeta($meta),
        };

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string>
     */
    private function formatModelMeta(array $meta): array
    {
        $lines = [];

        if (isset($meta['table'])) {
            $lines[] = "- **Table:** `{$meta['table']}`";
        }

        if (! empty($meta['fillable'])) {
            $fillable = implode('`, `', $meta['fillable']);
            $lines[] = "- **Fillable:** `{$fillable}`";
        }

        if (! empty($meta['guarded'])) {
            $guarded = implode('`, `', $meta['guarded']);
            $lines[] = "- **Guarded:** `{$guarded}`";
        }

        if (! empty($meta['casts'])) {
            $casts = [];
            foreach ($meta['casts'] as $key => $cast) {
                $casts[] = "`{$key}` => `{$cast}`";
            }
            $lines[] = "- **Casts:** ".implode(', ', $casts);
        }

        if (! empty($meta['relations'])) {
            $lines[] = "- **Relations:**";
            foreach ($meta['relations'] as $name => $relation) {
                $type = $relation['type'] ?? 'unknown';
                $related = $relation['related'] ?? 'unknown';
                $lines[] = "  - `{$name}`: {$type} → `{$related}`";
            }
        }

        if (isset($meta['factory'])) {
            $lines[] = "- **Factory:** `{$meta['factory']}`";
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string>
     */
    private function formatDomainServiceMeta(array $meta): array
    {
        $lines = [];

        if (! empty($meta['methods'])) {
            $methods = implode('`, `', $meta['methods']);
            $lines[] = "- **Methods:** `{$methods}`";
        }

        if (! empty($meta['dependencies'])) {
            $deps = implode('`, `', $meta['dependencies']);
            $lines[] = "- **Dependencies:** `{$deps}`";
        }

        if (isset($meta['interface'])) {
            $lines[] = "- **Interface:** `{$meta['interface']}`";
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string>
     */
    private function formatBladeViewMeta(array $meta): array
    {
        $lines = [];

        if (isset($meta['role'])) {
            $lines[] = "- **Role:** `{$meta['role']}`";
        }

        if (! empty($meta['variables'])) {
            $vars = implode('`, `', $meta['variables']);
            $lines[] = "- **Variables:** `{$vars}`";
        }

        if (isset($meta['extends'])) {
            $lines[] = "- **Extends:** `{$meta['extends']}`";
        }

        if (! empty($meta['includes'])) {
            $includes = implode('`, `', $meta['includes']);
            $lines[] = "- **Includes:** `{$includes}`";
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string>
     */
    private function formatConfigAreaMeta(array $meta): array
    {
        $lines = [];

        if (! empty($meta['keys'])) {
            $keys = implode('`, `', $meta['keys']);
            $lines[] = "- **Keys:** `{$keys}`";
        }

        if (! empty($meta['sections'])) {
            $sections = implode('`, `', $meta['sections']);
            $lines[] = "- **Sections:** `{$sections}`";
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string>
     */
    private function formatHttpEndpointMeta(array $meta): array
    {
        $lines = [];

        if (isset($meta['method'])) {
            $lines[] = "- **Method:** `{$meta['method']}`";
        }

        if (isset($meta['uri'])) {
            $lines[] = "- **URI:** `{$meta['uri']}`";
        }

        if (isset($meta['group'])) {
            $lines[] = "- **Group:** `{$meta['group']}`";
        }

        if (isset($meta['auth'])) {
            $lines[] = "- **Auth:** `{$meta['auth']}`";
        }

        if (! empty($meta['parameters'])) {
            $lines[] = "- **Parameters:**";
            foreach ($meta['parameters'] as $name => $param) {
                $type = $param['type'] ?? 'string';
                $required = $param['required'] ?? true ? 'required' : 'optional';
                $lines[] = "  - `{$name}` ({$type}, {$required})";
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string>
     */
    private function formatGenericMeta(array $meta): array
    {
        $lines = [];

        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $lines[] = "- **{$key}:** `{$value}`";
        }

        return $lines;
    }
}

