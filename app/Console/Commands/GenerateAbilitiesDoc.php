<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

class GenerateAbilitiesDoc extends Command
{
    protected $signature = 'docs:abilities';
    protected $description = 'Generate permissions/abilities documentation';

    public function handle(): int
    {
        $this->info('Generating abilities documentation...');

        $abilities = $this->scanPolicies();

        // JSON
        $jsonPath = base_path('docs/_generated/permissions.json');
        file_put_contents($jsonPath, json_encode($abilities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("✓ Generated: docs/_generated/permissions.json");

        // Markdown
        $mdPath = base_path('docs/_generated/permissions.md');
        $markdown = $this->generateMarkdown($abilities);
        file_put_contents($mdPath, $markdown);
        $this->info("✓ Generated: docs/_generated/permissions.md");

        return self::SUCCESS;
    }

    private function scanPolicies(): array
    {
        $abilities = [];
        $policiesPath = app_path('Policies');

        if (!is_dir($policiesPath)) {
            return [];
        }

        $files = File::files($policiesPath);

        foreach ($files as $file) {
            $className = 'App\\Policies\\' . $file->getFilenameWithoutExtension();

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                // Skip magic methods and constructor
                if (str_starts_with($method->getName(), '__')) {
                    continue;
                }

                $model = $this->extractModelFromPolicy($className);
                $ability = $method->getName();
                
                $docComment = $method->getDocComment() ?: null;
                $description = $this->extractDescription($docComment);

                $abilities[] = [
                    'policy' => class_basename($className),
                    'model' => $model,
                    'ability' => $ability,
                    'description' => $description,
                    'file' => 'app/Policies/' . $file->getFilename(),
                ];
            }
        }

        return $abilities;
    }

    private function extractModelFromPolicy(string $policyClass): string
    {
        // EntryPolicy → Entry
        $basename = class_basename($policyClass);
        return str_replace('Policy', '', $basename);
    }

    private function extractDescription(?string $docComment): string
    {
        if (!$docComment) {
            return '';
        }

        // Извлекаем первую строку комментария
        preg_match('/@description\s+(.+)/', $docComment, $matches);
        if (isset($matches[1])) {
            return trim($matches[1]);
        }

        // Или просто первая строка
        $lines = explode("\n", $docComment);
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B/*");
            if ($line && !str_starts_with($line, '@')) {
                return $line;
            }
        }

        return '';
    }

    private function generateMarkdown(array $abilities): string
    {
        $md = "# Permissions & Abilities\n\n";
        $md .= "> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:abilities` to update.\n\n";
        $md .= "_Last generated: " . now()->toDateTimeString() . "_\n\n";

        if (empty($abilities)) {
            $md .= "_No policies found._\n";
            return $md;
        }

        $grouped = collect($abilities)->groupBy('model');

        foreach ($grouped as $model => $modelAbilities) {
            $md .= "## {$model}\n\n";
            $md .= "| Ability | Description | Policy File |\n";
            $md .= "|---------|-------------|-------------|\n";

            foreach ($modelAbilities as $ability) {
                $md .= sprintf(
                    "| `%s` | %s | `%s` |\n",
                    $ability['ability'],
                    $ability['description'] ?: '_No description_',
                    $ability['file']
                );
            }

            $md .= "\n";
        }

        $md .= "## Usage\n\n";
        $md .= "```php\n";
        $md .= "// In controller\n";
        $md .= "\$this->authorize('update', \$entry);\n\n";
        $md .= "// In blade\n";
        $md .= "@can('update', \$entry)\n";
        $md .= "    <!-- Edit button -->\n";
        $md .= "@endcan\n";
        $md .= "```\n";

        return $md;
    }
}

