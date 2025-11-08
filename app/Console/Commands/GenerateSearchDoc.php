<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateSearchDoc extends Command
{
    protected $signature = 'docs:search';
    protected $description = 'Generate Elasticsearch mappings documentation';

    public function handle(): int
    {
        $this->info('Generating search mappings documentation...');

        $mappings = $this->getElasticsearchMappings();

        // JSON
        $jsonPath = base_path('docs/_generated/search-mappings.json');
        file_put_contents($jsonPath, json_encode($mappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("✓ Generated: docs/_generated/search-mappings.json");

        // Markdown
        $mdPath = base_path('docs/_generated/search-mappings.md');
        $markdown = $this->generateMarkdown($mappings);
        file_put_contents($mdPath, $markdown);
        $this->info("✓ Generated: docs/_generated/search-mappings.md");

        return self::SUCCESS;
    }

    private function getElasticsearchMappings(): array
    {
        // Если есть config/search.php, используем его
        if (config('search.mappings')) {
            return config('search.mappings');
        }

        // Иначе возвращаем дефолтный маппинг
        return [
            'entries' => [
                'properties' => [
                    'id' => ['type' => 'long'],
                    'title' => ['type' => 'text', 'analyzer' => 'russian'],
                    'content' => ['type' => 'text', 'analyzer' => 'russian'],
                    'slug' => ['type' => 'keyword'],
                    'post_type' => ['type' => 'keyword'],
                    'terms' => ['type' => 'keyword'],
                    'published_at' => ['type' => 'date'],
                    'status' => ['type' => 'keyword'],
                ],
            ],
        ];
    }

    private function generateMarkdown(array $mappings): string
    {
        $md = "# Elasticsearch Mappings\n\n";
        $md .= "> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:search` to update.\n\n";
        $md .= "_Last generated: " . now()->toDateTimeString() . "_\n\n";

        foreach ($mappings as $index => $mapping) {
            $md .= "## Index: `{$index}`\n\n";

            if (!isset($mapping['properties'])) {
                $md .= "_No properties defined._\n\n";
                continue;
            }

            $md .= "| Field | Type | Analyzer | Description |\n";
            $md .= "|-------|------|----------|-------------|\n";

            foreach ($mapping['properties'] as $field => $config) {
                $type = $config['type'] ?? 'unknown';
                $analyzer = $config['analyzer'] ?? '-';
                $description = $this->getFieldDescription($field);

                $md .= sprintf(
                    "| `%s` | %s | %s | %s |\n",
                    $field,
                    $type,
                    $analyzer,
                    $description
                );
            }

            $md .= "\n";
        }

        $md .= "## Index Settings\n\n";
        $md .= "```json\n";
        $md .= json_encode($this->getIndexSettings(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $md .= "```\n\n";

        $md .= "## Setup\n\n";
        $md .= "To create indices:\n\n";
        $md .= "```bash\n";
        $md .= "php artisan search:setup\n";
        $md .= "```\n\n";

        $md .= "To reindex data:\n\n";
        $md .= "```bash\n";
        $md .= "php artisan search:reindex\n";
        $md .= "```\n";

        return $md;
    }

    private function getFieldDescription(string $field): string
    {
        $descriptions = [
            'id' => 'Entry ID',
            'title' => 'Entry title (searchable)',
            'content' => 'Entry content (searchable)',
            'slug' => 'Entry slug (exact match)',
            'post_type' => 'Post type slug (filter)',
            'terms' => 'Associated term slugs (filter)',
            'published_at' => 'Publication date (sort)',
            'status' => 'Entry status (filter)',
        ];

        return $descriptions[$field] ?? '';
    }

    private function getIndexSettings(): array
    {
        return [
            'number_of_shards' => 1,
            'number_of_replicas' => 1,
            'analysis' => [
                'analyzer' => [
                    'russian' => [
                        'type' => 'standard',
                        'stopwords' => '_russian_',
                    ],
                ],
            ],
        ];
    }
}

