<?php

namespace App\Console\Commands;

use App\Domain\Routing\PathReservationService;
use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use Illuminate\Console\Command;

class RoutesReleaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'routes:release {path : The path to release} {source : The source identifier that owns the reservation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release a reserved URL path';

    /**
     * Execute the console command.
     */
    public function handle(PathReservationService $service): int
    {
        $path = $this->argument('path');
        $source = $this->argument('source');

        try {
            $service->releasePath($path, $source);
            $this->info("Path '{$path}' has been released.");
            return Command::SUCCESS;
        } catch (ForbiddenReservationRelease $e) {
            $this->error("Cannot release path '{$e->path}': it is reserved by '{$e->owner}', not '{$e->attemptedSource}'.");
            return Command::FAILURE;
        }
    }
}
