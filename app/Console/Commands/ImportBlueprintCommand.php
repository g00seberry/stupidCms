<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\PostType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportBlueprintCommand extends Command
{
    /**
     * Сигнатура команды.
     *
     * @var string
     */
    protected $signature = 'blueprint:import
                            {file : JSON file path}
                            {--post-type= : PostType slug (for full blueprints)}
                            {--force : Overwrite existing blueprint}';

    /**
     * Описание команды.
     *
     * @var string
     */
    protected $description = 'Import Blueprint schema from JSON';

    /**
     * Выполнить команду.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $data = json_decode(File::get($filePath), true);

        if (!$data) {
            $this->error('Invalid JSON file');
            return 1;
        }

        // Проверка существования
        $existing = Blueprint::where('slug', $data['slug'])->first();

        if ($existing && !$this->option('force')) {
            $this->error("Blueprint '{$data['slug']}' already exists. Use --force to overwrite.");
            return 1;
        }

        DB::transaction(function () use ($data, $existing) {
            if ($existing) {
                $existing->paths()->delete();
                $blueprint = $existing;
                $blueprint->update([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                ]);
            } else {
                $postTypeId = null;
                if ($data['type'] === 'full') {
                    $postTypeSlug = $this->option('post-type');
                    if (!$postTypeSlug) {
                        throw new \InvalidArgumentException('--post-type required for full blueprints');
                    }
                    $postType = PostType::where('slug', $postTypeSlug)->firstOrFail();
                    $postTypeId = $postType->id;
                }

                $blueprint = Blueprint::create([
                    'post_type_id' => $postTypeId,
                    'slug' => $data['slug'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'type' => $data['type'],
                ]);
            }

            // Create paths
            foreach ($data['paths'] as $pathData) {
                Path::create([
                    'blueprint_id' => $blueprint->id,
                    ...$pathData,
                ]);
            }
        });

        $this->info("Blueprint '{$data['slug']}' imported successfully");

        return 0;
    }
}

