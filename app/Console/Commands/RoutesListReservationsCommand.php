<?php

namespace App\Console\Commands;

use App\Models\RouteReservation;
use Illuminate\Console\Command;

class RoutesListReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'routes:list-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all reserved paths';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $reservations = RouteReservation::orderBy('path')->get();

        if ($reservations->isEmpty()) {
            $this->info('No path reservations found.');
            return Command::SUCCESS;
        }

        $this->table(
            ['Path', 'Source', 'Reason', 'Created At'],
            $reservations->map(fn($r) => [
                $r->path,
                $r->source,
                $r->reason ?? '-',
                $r->created_at->format('Y-m-d H:i:s'),
            ])->toArray()
        );

        $this->info("Total: {$reservations->count()} reservation(s).");

        return Command::SUCCESS;
    }
}
