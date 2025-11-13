<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Documentation\Generators\IndexGenerator;
use App\Documentation\Generators\IndexPageGenerator;
use App\Documentation\Generators\MarkdownGenerator;
use App\Documentation\ScannerManager;
use Illuminate\Console\Command;

final class GenerateDocsCommand extends Command
{
    protected $signature = 'docs:generate 
                            {--type= : Generate documentation for specific type only}
                            {--force : Overwrite existing files}
                            {--cache : Use cached scan results}';

    protected $description = 'Generate documentation from codebase';

    public function handle(ScannerManager $scannerManager): int
    {
        $this->info('Starting documentation generation...');

        // Определяем тип для сканирования
        $type = $this->option('type');

        // Запускаем сканирование
        $this->info('Scanning codebase...');
        if ($type) {
            $entities = $scannerManager->scanType($type);
            $this->info("Found ".count($entities)." entities of type '{$type}'");
        } else {
            $entities = $scannerManager->scanAll();
            $this->info('Found '.count($entities).' entities total');
        }

        if (empty($entities)) {
            $this->warn('No entities found. Nothing to generate.');
            return self::FAILURE;
        }

        // Группируем по типам для отчета
        $byType = [];
        foreach ($entities as $entity) {
            $byType[$entity->type] = ($byType[$entity->type] ?? 0) + 1;
        }

        $this->table(['Type', 'Count'], array_map(fn($t, $c) => [$t, $c], array_keys($byType), $byType));

        // Генерируем Markdown
        $this->info('Generating Markdown files...');
        $markdownGenerator = new MarkdownGenerator();
        $markdownGenerator->generate($entities);
        $this->info('Markdown files generated.');

        // Генерируем индекс
        $this->info('Generating index...');
        $indexGenerator = new IndexGenerator();
        $indexGenerator->generate($entities);
        $this->info('Index generated.');

        // Генерируем индексную страницу
        $this->info('Generating index page...');
        $indexPageGenerator = new IndexPageGenerator();
        $indexPageGenerator->generate($entities);
        $this->info('Index page generated.');

        $this->info('Documentation generation completed!');
        $this->info('Output directory: '.config('docs.output.markdown_dir', 'docs/generated'));

        return self::SUCCESS;
    }
}

