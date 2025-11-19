<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Blueprint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReindexBlueprintEntries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Создать новый экземпляр джоба.
     */
    public function __construct(
        public int $blueprintId
    ) {}

    /**
     * Выполнить джоб.
     */
    public function handle(): void
    {
        $blueprint = Blueprint::find($this->blueprintId);

        if (!$blueprint) {
            Log::warning("Blueprint {$this->blueprintId} not found for reindexing");
            return;
        }

        $count = 0;

        $blueprint->entries()->chunk(100, function ($entries) use (&$count) {
            foreach ($entries as $entry) {
                $entry->syncDocumentIndex();
                $count++;
            }
        });

        Log::info("Reindexed {$count} entries for Blueprint {$blueprint->slug}");
    }
}

