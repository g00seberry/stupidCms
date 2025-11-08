<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Commands;

use App\Domain\Plugins\Exceptions\InvalidPluginManifest;
use App\Domain\Plugins\Exceptions\RoutesReloadFailed;
use App\Domain\Plugins\Services\PluginsSynchronizer;
use Illuminate\Console\Command;
use Throwable;

final class PluginsSyncCommand extends Command
{
    protected $signature = 'plugins:sync';

    protected $description = 'Synchronize plugins from filesystem into database.';

    public function __construct(private readonly PluginsSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $summary = $this->synchronizer->sync();
        } catch (InvalidPluginManifest|RoutesReloadFailed $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            report($exception);

            $this->error('Unexpected error during plugins sync: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $this->components->info(sprintf(
            'Plugins sync complete. Added: %d, Updated: %d, Removed: %d',
            $summary['added'],
            $summary['updated'],
            $summary['removed'],
        ));

        if ($summary['providers'] !== []) {
            $this->components->twoColumnDetail('Providers discovered', implode(', ', $summary['providers']));
        }

        return Command::SUCCESS;
    }
}

