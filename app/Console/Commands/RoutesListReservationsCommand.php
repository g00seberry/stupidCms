<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReservedRoute;
use Illuminate\Console\Command;

/**
 * Команда для вывода списка всех зарезервированных путей.
 *
 * Выводит таблицу со всеми резервациями путей из таблицы reserved_routes.
 *
 * @package App\Console\Commands
 */
class RoutesListReservationsCommand extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'routes:list-reservations';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'List all reserved paths';

    /**
     * Выполнить консольную команду.
     *
     * Выводит таблицу с зарезервированными путями (path, kind, source, created_at).
     *
     * @return int Код возврата (0 = успех, 1 = ошибка)
     */
    public function handle(): int
    {
        $reservations = ReservedRoute::orderBy('path')->get();

        if ($reservations->isEmpty()) {
            $this->info('No path reservations found.');
            return Command::SUCCESS;
        }

        $this->table(
            ['Path', 'Kind', 'Source', 'Created At'],
            $reservations->map(fn($r) => [
                $r->path,
                $r->kind,
                $r->source,
                $r->created_at->format('Y-m-d H:i:s'),
            ])->toArray()
        );

        $this->info("Total: {$reservations->count()} reservation(s).");

        return Command::SUCCESS;
    }
}
