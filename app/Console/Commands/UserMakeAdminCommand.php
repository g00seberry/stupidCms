<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Команда для назначения пользователя администратором.
 *
 * Устанавливает is_admin=1 для указанного пользователя по email.
 *
 * @package App\Console\Commands
 */
class UserMakeAdminCommand extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'user:make-admin {email : The email of the user to make admin}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Make a user an administrator by setting is_admin=1';

    /**
     * Выполнить консольную команду.
     *
     * Находит пользователя по email и устанавливает is_admin=1.
     *
     * @return int Код возврата (0 = успех, 1 = ошибка)
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
