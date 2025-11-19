<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Console\Command;

class MigrateEntriesToBlueprintsCommand extends Command
{
    /**
     * Сигнатура команды.
     *
     * @var string
     */
    protected $signature = 'entries:migrate-to-blueprints
                            {--dry-run : Simulate without changes}';

    /**
     * Описание команды.
     *
     * @var string
     */
    protected $description = 'Migrate existing entries to Blueprint system';

    /**
     * Выполнить команду.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be made');
        }

        $postTypes = PostType::all();

        foreach ($postTypes as $postType) {
            $this->info("Processing PostType: {$postType->slug}");

            // Создать или найти default Blueprint
            $blueprint = Blueprint::firstOrCreate(
                [
                    'post_type_id' => $postType->id,
                    'is_default' => true,
                ],
                [
                    'slug' => "{$postType->slug}_default",
                    'name' => "{$postType->name} Default",
                    'description' => "Default blueprint for {$postType->name}",
                    'type' => 'full',
                ]
            );

            $this->line("  Blueprint: {$blueprint->slug}");

            // Найти Entries без blueprint_id
            $entries = Entry::where('post_type_id', $postType->id)
                ->whereNull('blueprint_id')
                ->get();

            if ($entries->isEmpty()) {
                $this->line("  No entries to migrate");
                continue;
            }

            $this->line("  Found {$entries->count()} entries to migrate");

            if (!$dryRun) {
                foreach ($entries as $entry) {
                    $entry->update(['blueprint_id' => $blueprint->id]);
                }

                $this->info("  ✓ Migrated {$entries->count()} entries");
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info('DRY RUN completed. Run without --dry-run to apply changes.');
        } else {
            $this->info('Migration completed successfully!');
            $this->info('Run: php artisan entries:reindex --queue');
        }

        return 0;
    }
}

