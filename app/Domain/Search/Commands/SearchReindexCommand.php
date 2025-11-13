<?php

declare(strict_types=1);

namespace App\Domain\Search\Commands;

use App\Domain\Search\IndexManager;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Команда для реиндексации поискового индекса.
 *
 * @package App\Domain\Search\Commands
 */
final class SearchReindexCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'search:reindex';

    /**
     * @var string
     */
    protected $description = 'Rebuild search indices for entries';

    /**
     * Выполнить реиндексацию.
     *
     * @param \App\Domain\Search\IndexManager $indexManager Менеджер индексов
     * @return int Код возврата (Command::SUCCESS или Command::FAILURE)
     */
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


