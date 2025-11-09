<?php

namespace App\Console\Commands;

use App\Models\Entry;
use App\Domain\Entries\EntrySlugService;
use App\Models\EntrySlug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEntrySlugsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:slugs:backfill {--chunk=100 : Количество записей для обработки за раз}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Заполнить историю slug для всех записей (backfill)';

    public function __construct(
        private EntrySlugService $entrySlugService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $total = Entry::count();
        $processed = 0;
        $created = 0;
        $fixed = 0;

        $this->info("Найдено записей: {$total}");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Entry::chunk($chunkSize, function ($entries) use (&$processed, &$created, &$fixed, $bar) {
            foreach ($entries as $entry) {
                // Проверяем наличие текущей записи в истории
                $currentSlug = $this->entrySlugService->currentSlug($entry->id);

                if ($currentSlug === null) {
                    // Нет текущей записи - создаём (сервис сам использует транзакции)
                    $this->entrySlugService->onCreated($entry);
                    $created++;
                } elseif ($currentSlug !== $entry->slug) {
                    // Текущий slug в истории не совпадает с slug в entry - исправляем
                    // Используем onUpdated для корректного обновления истории
                    // Не диспатчим событие при backfill (не создаём редиректы)
                    $this->entrySlugService->onUpdated($entry, $currentSlug, false);
                    $fixed++;
                } else {
                    // Проверяем, нет ли множественных is_current=1
                    $currentCount = EntrySlug::where('entry_id', $entry->id)
                        ->where('is_current', true)
                        ->count();

                    if ($currentCount > 1) {
                        // Исправляем множественные флаги (сервис сам использует транзакции)
                        DB::transaction(function () use ($entry) {
                            // Блокируем строки для защиты от гонок
                            EntrySlug::where('entry_id', $entry->id)
                                ->lockForUpdate()
                                ->get();
                            
                            // Оставляем только один is_current=1 для текущего slug
                            DB::statement(
                                "UPDATE entry_slugs SET is_current = CASE WHEN slug = ? THEN 1 ELSE 0 END WHERE entry_id = ?",
                                [$entry->slug, $entry->id]
                            );
                        });
                        $fixed++;
                    }
                }

                $processed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Обработано: {$processed}");
        $this->info("Создано новых записей истории: {$created}");
        $this->info("Исправлено проблем: {$fixed}");

        return Command::SUCCESS;
    }
}

