<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserMakeAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:make-admin {email : The email of the user to make admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a user an administrator by setting is_admin=1';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        if ($user->is_admin) {
            $this->info("User '{$email}' is already an administrator.");
            return Command::SUCCESS;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("User '{$email}' has been made an administrator.");

        return Command::SUCCESS;
    }
}
