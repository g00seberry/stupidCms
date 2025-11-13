<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Options\OptionsRepository;
use App\Models\Entry;
use Illuminate\Console\Command;

/**
 * Команда для установки значения опции.
 *
 * Устанавливает значение опции через OptionsRepository.
 * Проверяет allow-list из конфига и валидирует специальные опции
 * (например, site:home_entry_id - проверка существования записи).
 *
 * @package App\Console\Commands
 */
class OptionsSetCommand extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'cms:options:set {namespace} {key} {value?}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Установить значение опции';

    /**
     * Выполнить консольную команду.
     *
     * Проверяет allow-list, парсит JSON-литералы и валидирует специальные опции.
     * Для site:home_entry_id проверяет существование записи.
     *
     * @param \App\Domain\Options\OptionsRepository $repository Репозиторий опций
     * @return int Код возврата (0 = успех, 1 = ошибка)
     */
    public function handle(OptionsRepository $repository): int
    {
        $namespace = $this->argument('namespace');
        $key = $this->argument('key');
        $input = $this->argument('value');

        // Проверка allow-list
        $allowed = config('options.allowed', []);
        if (!isset($allowed[$namespace]) || !in_array($key, $allowed[$namespace], true)) {
            $this->error("Опция {$namespace}:{$key} не разрешена. Проверьте config/options.php");
            return self::FAILURE;
        }

        // Парсинг JSON-литералов
        $value = null;
        if (!is_null($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                $value = $input;
            }
        }

        // Специальная валидация для site:home_entry_id
        if ($namespace === 'site' && $key === 'home_entry_id') {
            // Каст для home_entry_id: null или int
            $finalValue = is_null($value) ? null : (int) $value;
            
            if (!is_null($finalValue)) {
                if ($finalValue < 1) {
                    $this->error("ID записи должен быть положительным числом");
                    return self::FAILURE;
                }

                if (!Entry::query()->whereKey($finalValue)->exists()) {
                    $this->error("Запись с ID {$finalValue} не найдена");
                    return self::FAILURE;
                }
            }

            $repository->set($namespace, $key, $finalValue);
            $this->info("Опция {$namespace}:{$key} установлена в " . ($finalValue ?? 'null'));
            return self::SUCCESS;
        }

        // Для других опций - сохраняем как есть
        $repository->set($namespace, $key, $value);
        $this->info("Опция {$namespace}:{$key} установлена");
        return self::SUCCESS;
    }
}

