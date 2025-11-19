<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportBlueprintCommand extends Command
{
    /**
     * Сигнатура команды.
     *
     * @var string
     */
    protected $signature = 'blueprint:export
                            {slug : Blueprint slug}
                            {--output= : Output file path}';

    /**
     * Описание команды.
     *
     * @var string
     */
    protected $description = 'Export Blueprint schema to JSON';

    /**
     * Выполнить команду.
     */
    public function handle(): int
    {
        $slug = $this->argument('slug');
        $blueprint = Blueprint::where('slug', $slug)
            ->with(['paths'])
            ->firstOrFail();

        $export = [
            'slug' => $blueprint->slug,
            'name' => $blueprint->name,
            'description' => $blueprint->description,
            'type' => $blueprint->type,
            'paths' => $blueprint->ownPaths->map(fn($path) => [
                'name' => $path->name,
                'full_path' => $path->full_path,
                'data_type' => $path->data_type,
                'cardinality' => $path->cardinality,
                'is_indexed' => $path->is_indexed,
                'is_required' => $path->is_required,
                'ref_target_type' => $path->ref_target_type,
                'validation_rules' => $path->validation_rules,
                'ui_options' => $path->ui_options,
            ])->toArray(),
        ];

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $outputPath = $this->option('output') ?? storage_path("blueprints/{$slug}.json");

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $json);

        $this->info("Blueprint exported to: {$outputPath}");

        return 0;
    }
}

