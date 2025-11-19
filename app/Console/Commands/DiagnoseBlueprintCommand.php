<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use Illuminate\Console\Command;

class DiagnoseBlueprintCommand extends Command
{
    /**
     * Сигнатура команды.
     *
     * @var string
     */
    protected $signature = 'blueprint:diagnose {slug : Blueprint slug}';

    /**
     * Описание команды.
     *
     * @var string
     */
    protected $description = 'Diagnose Blueprint schema and show statistics';

    /**
     * Выполнить команду.
     */
    public function handle(): int
    {
        $blueprint = Blueprint::where('slug', $this->argument('slug'))
            ->with(['paths', 'entries'])
            ->firstOrFail();

        $this->info("Blueprint: {$blueprint->name} ({$blueprint->slug})");
        $this->info("Type: {$blueprint->type}");
        $this->newLine();

        // Paths
        $ownPaths = $blueprint->ownPaths;
        $materializedPaths = $blueprint->materializedPaths;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Own Paths', $ownPaths->count()],
                ['Materialized Paths', $materializedPaths->count()],
                ['Total Paths', $blueprint->paths->count()],
                ['Indexed Paths', $blueprint->paths->where('is_indexed', true)->count()],
                ['Required Paths', $blueprint->paths->where('is_required', true)->count()],
                ['Ref Paths', $blueprint->paths->where('data_type', 'ref')->count()],
                ['Embedded Blueprints', $blueprint->paths->where('data_type', 'blueprint')->count()],
                ['Entries', $blueprint->entries->count()],
            ]
        );

        $this->newLine();
        $this->info('Paths by data_type:');
        $byType = $blueprint->paths->groupBy('data_type');
        foreach ($byType as $type => $paths) {
            $this->line("  {$type}: {$paths->count()}");
        }

        return 0;
    }
}

