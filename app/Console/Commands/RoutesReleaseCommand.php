<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Routing\PathReservationService;
use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use Illuminate\Console\Command;

/**
 * Команда для освобождения зарезервированного пути.
 *
 * Освобождает путь, зарезервированный указанным источником.
 * Выбрасывает ForbiddenReservationRelease, если путь зарезервирован другим источником.
 *
 * @package App\Console\Commands
 */
class RoutesReleaseCommand extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'routes:release {path : The path to release} {source : The source identifier that owns the reservation}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Release a reserved URL path';

    /**
     * Выполнить консольную команду.
     *
     * Освобождает путь через PathReservationService.
     *
     * @param \App\Domain\Routing\PathReservationService $service Сервис резервации путей
     * @return int Код возврата (0 = успех, 1 = ошибка)
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
