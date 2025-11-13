<?php

declare(strict_types=1);

namespace App\Documentation\Generators;

use App\Documentation\Contracts\GeneratorInterface;
use App\Documentation\DocEntity;
use Illuminate\Support\Facades\File;

final class IndexPageGenerator implements GeneratorInterface
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

        // Генерируем индексную страницу
        $content = $this->generateIndexPage($byType, count($entities));

        File::put($this->outputDir.'/README.md', $content);
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
     * @param array<string, array<DocEntity>> $byType
     */
    private function generateIndexPage(array $byType, int $totalEntities): string
    {
        $lines = [
            '# Documentation Index',
            '',
            'Автоматически сгенерированная документация кодовой базы.',
            '',
            "**Всего сущностей:** {$totalEntities}",
            '',
            '## Содержание',
            '',
        ];

        // Список типов с описаниями
        $typeDescriptions = [
            'model' => 'Eloquent-модели для работы с БД',
            'domain_service' => 'Доменные сервисы, действия, репозитории',
            'blade_view' => 'Blade-шаблоны для рендеринга',
            'config_area' => 'Логические секции конфигурации',
            'concept' => 'Доменные концепции и идеи',
            'http_endpoint' => 'HTTP эндпоинты API',
        ];

        // Генерируем ссылки на файлы по типам
        foreach ($byType as $type => $entities) {
            $fileName = $this->getFileNameForType($type);
            $title = $this->getTitleForType($type);
            $description = $typeDescriptions[$type] ?? '';
            $count = count($entities);

            $lines[] = "### [{$title}](./{$fileName})";
            if ($description) {
                $lines[] = "{$description} ({$count} сущностей)";
            } else {
                $lines[] = "{$count} сущностей";
            }
            $lines[] = '';
        }

        // Быстрая навигация по сущностям
        $lines[] = '## Быстрая навигация';
        $lines[] = '';

        foreach ($byType as $type => $entities) {
            $fileName = $this->getFileNameForType($type);
            $title = $this->getTitleForType($type);

            $lines[] = "### {$title}";
            $lines[] = '';

            // Показываем первые 10 сущностей, остальные - ссылка "и еще N"
            $showCount = min(10, count($entities));
            $remaining = count($entities) - $showCount;

            for ($i = 0; $i < $showCount; $i++) {
                $entity = $entities[$i];
                $anchor = $this->generateAnchor($entity->name);
                $summary = $this->normalizeSummary($entity->summary);
                $lines[] = "- [{$entity->name}](./{$fileName}#{$anchor}) - {$summary}";
            }

            if ($remaining > 0) {
                $lines[] = "- *...и еще {$remaining} сущностей*";
            }

            $lines[] = '';
        }

        // Статистика по тегам
        $tags = $this->extractTags($byType);
        if (! empty($tags)) {
            $lines[] = '## Популярные теги';
            $lines[] = '';
            $lines[] = 'Документация также индексируется по тегам. См. [index.json](./index.json) для полного индекса.';
            $lines[] = '';
        }

        // Информация о генерации
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '**Сгенерировано:** '.now()->format('Y-m-d H:i:s');
        $lines[] = '';
        $lines[] = 'Для обновления документации выполните:';
        $lines[] = '```bash';
        $lines[] = 'php artisan docs:generate';
        $lines[] = '```';

        return implode("\n", $lines);
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

    private function generateAnchor(string $name): string
    {
        // Преобразуем имя в anchor для Markdown (lowercase, пробелы в дефисы)
        $anchor = strtolower($name);
        $anchor = preg_replace('/[^a-z0-9]+/', '-', $anchor);
        $anchor = trim($anchor, '-');

        return $anchor;
    }

    /**
     * @param array<string, array<DocEntity>> $byType
     * @return array<string, int>
     */
    private function extractTags(array $byType): array
    {
        $tags = [];

        foreach ($byType as $entities) {
            foreach ($entities as $entity) {
                foreach ($entity->tags as $tag) {
                    $tags[$tag] = ($tags[$tag] ?? 0) + 1;
                }
            }
        }

        arsort($tags);

        return array_slice($tags, 0, 20); // Топ 20 тегов
    }

    private function normalizeSummary(string $summary): string
    {
        // Убираем абсолютные пути Windows из summary
        $basePath = str_replace('\\', '/', base_path());
        $summary = str_replace('\\', '/', $summary);
        $summary = str_replace($basePath.'/', '', $summary);
        $summary = str_replace($basePath, '', $summary);

        return $summary;
    }
}

