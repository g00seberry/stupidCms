<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Options\OptionsRepository;
use Illuminate\Console\Command;

/**
 * Команда для получения значения опции.
 *
 * Выводит значение опции в формате JSON (STDOUT).
 *
 * @package App\Console\Commands
 */
class OptionsGetCommand extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'cms:options:get {namespace} {key}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Получить значение опции';

    /**
     * Выполнить консольную команду.
     *
     * Получает значение опции через OptionsRepository и выводит в STDOUT в формате JSON.
     *
     * @param \App\Domain\Options\OptionsRepository $repository Репозиторий опций
     * @return int Код возврата (0 = успех, 1 = ошибка)
     */
    public function handle(OptionsRepository $repository): int
    {
        $namespace = $this->argument('namespace');
        $key = $this->argument('key');

        $value = $repository->get($namespace, $key);

        // Вывод в STDOUT JSON-значения
        $this->line(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}

