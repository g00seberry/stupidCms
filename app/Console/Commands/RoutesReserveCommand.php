<?php

namespace App\Console\Commands;

use App\Domain\Routing\PathReservationService;
use App\Domain\Routing\Exceptions\InvalidPathException;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use Illuminate\Console\Command;

class RoutesReserveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'routes:reserve {path : The path to reserve} {source : The source identifier (e.g., system:feeds, plugin:shop)} {reason? : Optional reason for reservation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reserve a URL path to prevent content/routes from using it';

    /**
     * Execute the console command.
     */
    public function handle(PathReservationService $service): int
    {
        $path = $this->argument('path');
        $source = $this->argument('source');
        $reason = $this->argument('reason');

        try {
            $service->reservePath($path, $source, $reason);
            $this->info("Path '{$path}' has been reserved by '{$source}'.");
            if ($reason) {
                $this->line("Reason: {$reason}");
            }
            return Command::SUCCESS;
        } catch (InvalidPathException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        } catch (PathAlreadyReservedException $e) {
            $this->error("Path '{$e->path}' is already reserved by '{$e->owner}'.");
            return Command::FAILURE;
        }
    }
}
