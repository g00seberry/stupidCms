<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GenerateErdDoc extends Command
{
    protected $signature = 'docs:erd';
    protected $description = 'Generate ERD diagram documentation';

    public function handle(): int
    {
        $this->info('Generating ERD documentation...');

        $tables = $this->scanTables();

        // JSON Schema
        $jsonPath = base_path('docs/_generated/erd.json');
        file_put_contents($jsonPath, json_encode($tables, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("✓ Generated: docs/_generated/erd.json");

        // PlantUML (для генерации SVG)
        $pumlPath = base_path('docs/_generated/erd.puml');
        $plantuml = $this->generatePlantUML($tables);
        file_put_contents($pumlPath, $plantuml);
        $this->info("✓ Generated: docs/_generated/erd.puml");

        $this->info("\nTo generate SVG, run:");
        $this->comment("  plantuml docs/_generated/erd.puml");
        $this->comment("  # or use https://www.plantuml.com/plantuml/uml/");
        
        $mermaidPath = base_path('docs/_generated/erd.mmd');
        $mermaid = $this->generateMermaid($tables);
        file_put_contents($mermaidPath, $mermaid);
        $this->info("✓ Generated: docs/_generated/erd.mmd");

        return self::SUCCESS;
    }

    private function scanTables(): array
    {
        $tables = [];

        // Получаем список таблиц через нативный Laravel метод
        $tableNames = Schema::getTables();
        $tableNames = array_filter($tableNames, function ($table) {
            return $table['schema'] === 'cakes3';
        });
        foreach ($tableNames as $tableData) {
            $tableName = $tableData['name'];
            
            // Пропускаем служебные таблицы
            if (in_array($tableName, ['migrations', 'personal_access_tokens', 'password_reset_tokens'])) {
                continue;
            }

            try {
                $columns = Schema::getColumns($tableName);
                $indexes = Schema::getIndexes($tableName);
                $foreignKeys = Schema::getForeignKeys($tableName);

                $tables[$tableName] = [
                    'name' => $tableName,
                    'columns' => $columns,
                    'indexes' => $indexes,
                    'foreign_keys' => $foreignKeys,
                ];
            } catch (\Exception $e) {
                $this->warn("Could not scan table {$tableName}: {$e->getMessage()}");
                continue;
            }
        }

        return $tables;
    }

    private function generatePlantUML(array $tables): string
    {
        $puml = "@startuml ERD\n";
        $puml .= "!theme plain\n";
        $puml .= "skinparam linetype ortho\n\n";

        // Entities
        foreach ($tables as $table) {
            $puml .= "entity \"{$table['name']}\" as {$table['name']} {\n";

            foreach ($table['columns'] as $column) {
                $type = $column['type_name'];
                $nullable = $column['nullable'] ? '?' : '';
                $pk = '';

                // Check if PK
                foreach ($table['indexes'] as $index) {
                    if ($index['primary'] && in_array($column['name'], $index['columns'])) {
                        $pk = ' <<PK>>';
                        break;
                    }
                }

                $puml .= "  {$column['name']} : {$type}{$nullable}{$pk}\n";
            }

            $puml .= "}\n\n";
        }

        // Relationships
        foreach ($tables as $table) {
            foreach ($table['foreign_keys'] as $fk) {
                $from = $table['name'];
                $to = $fk['foreign_table'];
                $puml .= "{$from} }o--|| {$to}\n";
            }
        }

        $puml .= "@enduml\n";

        return $puml;
    }

    private function generateMermaid(array $tables): string
    {
        $mermaid = "erDiagram\n";

        foreach ($tables as $table) {
            $tableName = $table['name'];

            // Columns
            $mermaid .= "    {$tableName} {\n";

            foreach ($table['columns'] as $column) {
                $type = $column['type_name'];
                $name = $column['name'];
                $mermaid .= "        {$type} {$name}\n";
            }

            $mermaid .= "    }\n\n";
        }

        // Relationships
        foreach ($tables as $table) {
            foreach ($table['foreign_keys'] as $fk) {
                $from = $table['name'];
                $to = $fk['foreign_table'];
                $mermaid .= "    {$from} }o--|| {$to} : \"\"\n";
            }
        }

        return $mermaid;
    }
}

