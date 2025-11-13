<?php

declare(strict_types=1);

namespace App\Documentation\Generators;

use App\Documentation\Contracts\GeneratorInterface;
use App\Documentation\DocEntity;
use Illuminate\Support\Facades\File;

final class IndexGenerator implements GeneratorInterface
{
    private string $outputFile;

    public function __construct(?string $outputFile = null)
    {
        $this->outputFile = $outputFile ?? base_path(config('docs.output.index_file', 'docs/generated/index.json'));
    }

    /**
     * @param array<DocEntity> $entities
     */
    public function generate(array $entities): void
    {
        // Создаем директорию, если не существует
        $dir = dirname($this->outputFile);
        File::ensureDirectoryExists($dir);

        // Формируем структуру индекса
        $index = [
            'entities' => array_map(fn($e) => $e->toArray(), $entities),
            'by_type' => $this->indexByType($entities),
            'by_tag' => $this->indexByTag($entities),
            'generated_at' => now()->toIso8601String(),
        ];

        // Сохраняем JSON
        $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        File::put($this->outputFile, $json);
    }

    /**
     * @param array<DocEntity> $entities
     * @return array<string, array<string, mixed>>
     */
    private function indexByType(array $entities): array
    {
        $indexed = [];

        foreach ($entities as $entity) {
            $indexed[$entity->type][] = $entity->toArray();
        }

        return $indexed;
    }

    /**
     * @param array<DocEntity> $entities
     * @return array<string, array<string, mixed>>
     */
    private function indexByTag(array $entities): array
    {
        $indexed = [];

        foreach ($entities as $entity) {
            foreach ($entity->tags as $tag) {
                $indexed[$tag][] = [
                    'id' => $entity->id,
                    'type' => $entity->type,
                    'name' => $entity->name,
                    'summary' => $entity->summary,
                ];
            }
        }

        return $indexed;
    }
}

