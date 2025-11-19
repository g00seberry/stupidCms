<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReindexBlueprintEntries;
use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Console\Command;

class ReindexEntriesCommand extends Command
{
    /**
     * Сигнатура команды.
     *
     * @var string
     */
    protected $signature = 'entries:reindex
                            {--post-type= : Slug PostType}
                            {--blueprint= : Slug Blueprint}
                            {--queue : Use queue for async processing}';

    /**
     * Описание команды.
     *
     * @var string
     */
    protected $description = 'Reindex entries doc_values and doc_refs';

    /**
     * Выполнить команду.
     */
    public function handle(): int
    {
        $postTypeSlug = $this->option('post-type');
        $blueprintSlug = $this->option('blueprint');
        $useQueue = $this->option('queue');

        $query = Entry::whereNotNull('blueprint_id');

        if ($postTypeSlug) {
            $postType = PostType::where('slug', $postTypeSlug)->firstOrFail();
            $query->where('post_type_id', $postType->id);
            $this->info("Filtering by PostType: {$postTypeSlug}");
        }

        if ($blueprintSlug) {
            $blueprint = Blueprint::where('slug', $blueprintSlug)->firstOrFail();
            $query->where('blueprint_id', $blueprint->id);
            $this->info("Filtering by Blueprint: {$blueprintSlug}");
        }

        $total = $query->count();

        if ($total === 0) {
            $this->warn('No entries to reindex');
            return 0;
        }

        $this->info("Found {$total} entries to reindex");

        if ($useQueue) {
            // Group by blueprint_id and dispatch jobs
            $blueprintIds = $query->distinct('blueprint_id')->pluck('blueprint_id');

            foreach ($blueprintIds as $blueprintId) {
                dispatch(new ReindexBlueprintEntries($blueprintId));
                $this->info("Dispatched job for Blueprint ID: {$blueprintId}");
            }

            $this->info('Jobs dispatched successfully');
        } else {
            // Synchronous processing
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $query->chunkById(100, function ($entries) use ($bar) {
                foreach ($entries as $entry) {
                    $entry->syncDocumentIndex();
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
            $this->info('Reindexing completed');
        }

        return 0;
    }
}

