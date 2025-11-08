<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateMediaPipelineDoc extends Command
{
    protected $signature = 'docs:media';
    protected $description = 'Generate media pipeline documentation';

    public function handle(): int
    {
        $this->info('Generating media pipeline documentation...');

        $pipeline = $this->getMediaPipeline();

        // JSON
        $jsonPath = base_path('docs/_generated/media-pipeline.json');
        file_put_contents($jsonPath, json_encode($pipeline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("✓ Generated: docs/_generated/media-pipeline.json");

        // Markdown
        $mdPath = base_path('docs/_generated/media-pipeline.md');
        $markdown = $this->generateMarkdown($pipeline);
        file_put_contents($mdPath, $markdown);
        $this->info("✓ Generated: docs/_generated/media-pipeline.md");

        return self::SUCCESS;
    }

    private function getMediaPipeline(): array
    {
        return [
            'storage' => [
                'driver' => config('filesystems.default'),
                'disk' => config('filesystems.default'),
                'path' => config('filesystems.disks.' . config('filesystems.default') . '.root'),
            ],
            'upload' => [
                'max_size_mb' => 10,
                'allowed_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'],
                'path_pattern' => 'media/{year}/{month}/{day}',
            ],
            'variants' => [
                'thumbnail' => ['width' => 150, 'height' => 150, 'fit' => 'crop'],
                'medium' => ['width' => 600, 'height' => 600, 'fit' => 'max'],
                'large' => ['width' => 1200, 'height' => 1200, 'fit' => 'max'],
            ],
            'optimization' => [
                'enabled' => config('media.optimize', false),
                'quality' => config('media.quality', 80),
                'format' => config('media.format', 'webp'),
            ],
            'events' => [
                'MediaUploaded' => [
                    'listeners' => [
                        'GenerateMediaVariants',
                        'OptimizeImage',
                        'ExtractMetadata',
                    ],
                ],
            ],
            'jobs' => [
                'GenerateVariantsJob' => 'Generate image variants (thumbnails)',
                'OptimizeImageJob' => 'Optimize image quality and size',
            ],
        ];
    }

    private function generateMarkdown(array $pipeline): string
    {
        $md = "# Media Pipeline\n\n";
        $md .= "> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:media` to update.\n\n";
        $md .= "_Last generated: " . now()->toDateTimeString() . "_\n\n";

        $md .= "## Storage Configuration\n\n";
        $md .= "| Setting | Value |\n";
        $md .= "|---------|-------|\n";

        foreach ($pipeline['storage'] as $key => $value) {
            $md .= sprintf("| %s | `%s` |\n", $key, $value ?? 'null');
        }

        $md .= "\n## Upload Settings\n\n";
        $md .= "| Setting | Value |\n";
        $md .= "|---------|-------|\n";

        $md .= sprintf("| Max Size | %d MB |\n", $pipeline['upload']['max_size_mb']);
        $md .= sprintf("| Allowed MIME Types | %s |\n", implode(', ', $pipeline['upload']['allowed_mimes']));
        $md .= sprintf("| Path Pattern | `%s` |\n", $pipeline['upload']['path_pattern']);

        $md .= "\n## Image Variants\n\n";
        $md .= "| Variant | Width | Height | Fit |\n";
        $md .= "|---------|-------|--------|-----|\n";

        foreach ($pipeline['variants'] as $variant => $config) {
            $md .= sprintf(
                "| `%s` | %dpx | %dpx | %s |\n",
                $variant,
                $config['width'],
                $config['height'],
                $config['fit']
            );
        }

        $md .= "\n## Optimization\n\n";
        $md .= "```php\n";
        $md .= "enabled: " . ($pipeline['optimization']['enabled'] ? 'true' : 'false') . "\n";
        $md .= "quality: {$pipeline['optimization']['quality']}\n";
        $md .= "format: {$pipeline['optimization']['format']}\n";
        $md .= "```\n\n";

        $md .= "## Pipeline Flow\n\n";
        $md .= "```mermaid\n";
        $md .= "graph TD\n";
        $md .= "    A[Upload Media] --> B[Store File]\n";
        $md .= "    B --> C[Create Media Record]\n";
        $md .= "    C --> D[Trigger MediaUploaded Event]\n";
        $md .= "    D --> E[GenerateVariantsJob]\n";
        $md .= "    D --> F[OptimizeImageJob]\n";
        $md .= "    D --> G[ExtractMetadata]\n";
        $md .= "    E --> H[Create MediaVariant Records]\n";
        $md .= "    F --> I[Optimize Original]\n";
        $md .= "    G --> J[Update Media.meta_json]\n";
        $md .= "```\n\n";

        $md .= "## Events & Listeners\n\n";

        foreach ($pipeline['events'] as $event => $data) {
            $md .= "### `{$event}`\n\n";
            $md .= "**Listeners**:\n\n";

            foreach ($data['listeners'] as $listener) {
                $md .= "- `{$listener}`\n";
            }

            $md .= "\n";
        }

        $md .= "## Jobs\n\n";

        foreach ($pipeline['jobs'] as $job => $description) {
            $md .= "### `{$job}`\n\n";
            $md .= "{$description}\n\n";
            $md .= "**Queue**: `default`\n\n";
        }

        return $md;
    }
}

