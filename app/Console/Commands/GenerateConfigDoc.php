<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateConfigDoc extends Command
{
    protected $signature = 'docs:config';
    protected $description = 'Generate configuration documentation';

    public function handle(): int
    {
        $this->info('Generating config documentation...');

        $configs = $this->scanConfigs();

        // JSON
        $jsonPath = base_path('docs/_generated/config.json');
        file_put_contents($jsonPath, json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("✓ Generated: docs/_generated/config.json");

        // Markdown
        $mdPath = base_path('docs/_generated/config.md');
        $markdown = $this->generateMarkdown($configs);
        file_put_contents($mdPath, $markdown);
        $this->info("✓ Generated: docs/_generated/config.md");

        return self::SUCCESS;
    }

    private function scanConfigs(): array
    {
        $configs = [];
        $configPath = config_path();
        $files = File::files($configPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $configName = $file->getFilenameWithoutExtension();
            $configData = config($configName);

            if (!is_array($configData)) {
                continue;
            }

            $configs[$configName] = $this->flattenConfig($configData);
        }

        return $configs;
    }

    private function flattenConfig(array $config, string $prefix = ''): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenConfig($value, $fullKey));
            } else {
                $result[$fullKey] = [
                    'value' => $value,
                    'type' => gettype($value),
                    'env' => $this->findEnvVariable($value),
                ];
            }
        }

        return $result;
    }

    private function findEnvVariable($value): ?string
    {
        // Простая эвристика: если значение пусто или похоже на default, ищем ENV
        // В реальности нужно парсить config файлы, но это сложно
        return null;
    }

    private function generateMarkdown(array $configs): string
    {
        $md = "# Configuration Reference\n\n";
        $md .= "> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:config` to update.\n\n";
        $md .= "_Last generated: " . now()->toDateTimeString() . "_\n\n";

        foreach ($configs as $configName => $configValues) {
            $md .= "## {$configName}\n\n";
            $md .= "**File**: `config/{$configName}.php`\n\n";

            if (empty($configValues)) {
                $md .= "_Empty configuration._\n\n";
                continue;
            }

            $md .= "| Key | Value | Type |\n";
            $md .= "|-----|-------|------|\n";

            foreach ($configValues as $key => $data) {
                $value = $this->formatValue($data['value']);
                $md .= sprintf(
                    "| `%s` | %s | %s |\n",
                    $key,
                    $value,
                    $data['type']
                );
            }

            $md .= "\n";
        }

        $md .= "## Environment Variables\n\n";
        $md .= "See `.env.example` for available environment variables.\n\n";
        $md .= "Key config variables:\n\n";
        $md .= "```env\n";
        $md .= "APP_NAME=\"stupidCms\"\n";
        $md .= "APP_ENV=production\n";
        $md .= "APP_DEBUG=false\n";
        $md .= "APP_URL=https://api.stupidcms.local\n\n";
        $md .= "DB_CONNECTION=mysql\n";
        $md .= "DB_HOST=127.0.0.1\n";
        $md .= "DB_DATABASE=stupidcms\n\n";
        $md .= "JWT_SECRET=<secret>\n";
        $md .= "JWT_ALGO=HS256\n\n";
        $md .= "ELASTICSEARCH_ENABLED=true\n";
        $md .= "ELASTICSEARCH_HOSTS=localhost:9200\n";
        $md .= "```\n";

        return $md;
    }

    private function formatValue($value): string
    {
        if ($value === null) {
            return '_null_';
        }

        if (is_bool($value)) {
            return $value ? '`true`' : '`false`';
        }

        if (is_string($value)) {
            if (strlen($value) > 50) {
                return '`' . substr($value, 0, 47) . '...`';
            }
            return "`{$value}`";
        }

        if (is_numeric($value)) {
            return "`{$value}`";
        }

        return '_' . gettype($value) . '_';
    }
}

