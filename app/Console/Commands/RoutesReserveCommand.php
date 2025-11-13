<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Routing\PathReservationService;
use App\Domain\Routing\Exceptions\InvalidPathException;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use Illuminate\Console\Command;

/**
 * Команда для резервации URL пути.
 *
 * Резервирует путь для предотвращения использования его контентом или маршрутами.
 * Выбрасывает InvalidPathException при невалидном пути или PathAlreadyReservedException
 * при попытке зарезервировать уже занятый путь.
 *
 * @package App\Console\Commands
 */
class RoutesReserveCommand extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'routes:reserve {path : The path to reserve} {source : The source identifier (e.g., system:feeds, plugin:shop)} {reason? : Optional reason for reservation}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Reserve a URL path to prevent content/routes from using it';

    /**
     * Выполнить консольную команду.
     *
     * Резервирует путь через PathReservationService.
     *
     * @param \App\Domain\Routing\PathReservationService $service Сервис резервации путей
     * @return int Код возврата (0 = успех, 1 = ошибка)
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
