<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Models\Entry;
use Illuminate\Console\Command;

class ValidateBlueprintMigrationCommand extends Command
{
    /**
     * Сигнатура команды.
     *
     * @var string
     */
    protected $signature = 'entries:validate-migration';

    /**
     * Описание команды.
     *
     * @var string
     */
    protected $description = 'Validate Blueprint migration results';

    /**
     * Выполнить команду.
     */
    public function handle(): int
    {
        $this->info('Validating Blueprint migration...');
        $this->newLine();

        // 1. Проверка Entry без blueprint_id
        $orphanEntries = Entry::whereNull('blueprint_id')->count();

        if ($orphanEntries > 0) {
            $this->error("✗ Found {$orphanEntries} entries without blueprint_id");
        } else {
            $this->info('✓ All entries have blueprint_id');
        }

        // 2. Проверка Blueprint без PostType
        $orphanBlueprints = Blueprint::where('type', 'full')
            ->whereNull('post_type_id')
            ->count();

        if ($orphanBlueprints > 0) {
            $this->error("✗ Found {$orphanBlueprints} full blueprints without post_type_id");
        } else {
            $this->info('✓ All full blueprints have post_type_id');
        }

        // 3. Статистика индексации
        $totalEntries = Entry::count();
        $indexedCount = Entry::whereHas('values')->count();

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Entries', $totalEntries],
                ['Entries with indexes', $indexedCount],
                ['Coverage', round(($indexedCount / ($totalEntries ?: 1)) * 100, 2) . '%'],
            ]
        );

        // 4. Рекомендации
        $this->newLine();

        if ($orphanEntries > 0) {
            $this->warn('Run: php artisan entries:migrate-to-blueprints');
        }

        if ($indexedCount < $totalEntries) {
            $this->warn('Run: php artisan entries:reindex --queue');
        }

        return $orphanEntries === 0 && $orphanBlueprints === 0 ? 0 : 1;
    }
}

