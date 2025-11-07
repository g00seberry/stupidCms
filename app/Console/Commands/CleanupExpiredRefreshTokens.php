<?php

namespace App\Console\Commands;

use App\Domain\Auth\RefreshTokenRepository;
use Illuminate\Console\Command;

class CleanupExpiredRefreshTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:cleanup-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired refresh tokens from the database';

    /**
     * Execute the console command.
     */
    public function handle(RefreshTokenRepository $repo): int
    {
        $this->info('Cleaning up expired refresh tokens...');

        $deleted = $repo->deleteExpired();

        if ($deleted > 0) {
            $this->info("Deleted {$deleted} expired refresh tokens.");
        } else {
            $this->info('No expired tokens found.');
        }

        return Command::SUCCESS;
    }
}
