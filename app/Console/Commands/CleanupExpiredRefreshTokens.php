<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Auth\RefreshTokenRepository;
use Illuminate\Console\Command;

/**
 * Команда для очистки истёкших refresh токенов.
 *
 * Удаляет из базы данных все refresh токены, срок действия которых истёк.
 *
 * @package App\Console\Commands
 */
class CleanupExpiredRefreshTokens extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'auth:cleanup-tokens';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Delete expired refresh tokens from the database';

    /**
     * Выполнить консольную команду.
     *
     * Удаляет истёкшие refresh токены через RefreshTokenRepository.
     *
     * @param \App\Domain\Auth\RefreshTokenRepository $repo Репозиторий refresh токенов
     * @return int Код возврата (0 = успех, 1 = ошибка)
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
