<?php

declare(strict_types=1);

namespace App\Domain\Search\Commands;

use App\Domain\Search\IndexManager;
use Illuminate\Console\Command;
use RuntimeException;

final class SearchReindexCommand extends Command
{
    protected $signature = 'search:reindex';
    protected $description = 'Rebuild search indices for entries';

    public function handle(IndexManager $indexManager): int
    {
        $this->info('Starting search reindex...');

        try {
            $index = $indexManager->reindex();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Reindex completed. Active index: %s', $index));

        return self::SUCCESS;
    }
}


