<?php

namespace App\Console\Commands;

use App\Domain\Options\OptionsRepository;
use Illuminate\Console\Command;

class OptionsGetCommand extends Command
{
    protected $signature = 'cms:options:get {namespace} {key}';
    protected $description = 'Получить значение опции';

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

