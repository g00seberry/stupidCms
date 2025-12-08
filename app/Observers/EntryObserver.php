<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Sanitizer\RichTextSanitizer;
use App\Models\DocRef;
use App\Models\DocValue;
use App\Models\Entry;
use App\Services\Entry\EntryIndexer;
use Illuminate\Support\Facades\Log;

/**
 * Observer для модели Entry.
 *
 * Обрабатывает события жизненного цикла Entry:
 * - Санитизация HTML полей (body_html, excerpt_html) в data_json
 * - Автоматическая индексация Entry при сохранении
 *
 * @package App\Observers
 */
class EntryObserver
{
    /**
     * @param \App\Domain\Sanitizer\RichTextSanitizer $sanitizer Санитизатор HTML
     * @param \App\Services\Entry\EntryIndexer $indexer Сервис индексации Entry
     */
    public function __construct(
        private RichTextSanitizer $sanitizer,
        private EntryIndexer $indexer,
    ) {}

    /**
     * Обработать событие "creating" для Entry.
     *
     * Санитизирует HTML поля.
     *
     * @param \App\Models\Entry $entry Создаваемая запись
     * @return void
     */
    public function creating(Entry $entry): void
    {
        $this->sanitizeRichTextFields($entry);
    }

    /**
     * Обработать событие "updating" для Entry.
     *
     * Санитизирует HTML поля при изменении data_json.
     *
     * @param \App\Models\Entry $entry Обновляемая запись
     * @return void
     */
    public function updating(Entry $entry): void
    {
        // Санитизируем richtext поля при изменении data_json
        if ($entry->isDirty('data_json')) {
            $this->sanitizeRichTextFields($entry);
        }
    }

    /**
     * Handle the Entry "saved" event.
     *
     * Автоматическая индексация Entry при сохранении.
     *
     * @param Entry $entry
     * @return void
     */
    public function saved(Entry $entry): void
    {
        // Индексация только если PostType имеет blueprint
        if ($entry->postType?->blueprint_id) {
            try {
                $this->indexer->index($entry);
            } catch (\Exception $e) {
                Log::error("Ошибка автоиндексации Entry {$entry->id}: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Handle the Entry "deleted" event.
     *
     * Очистка индексов при удалении Entry.
     *
     * @param Entry $entry
     * @return void
     */
    public function deleted(Entry $entry): void
    {
        // Очистить индексы (CASCADE в БД, но на всякий случай)
        DocValue::where('entry_id', $entry->id)->delete();
        DocRef::where('entry_id', $entry->id)->delete();
    }


    /**
     * Санитизировать richtext поля (body_html, excerpt_html) из data_json.
     *
     * Сохраняет очищенный HTML в body_html_sanitized/excerpt_html_sanitized
     * для безопасного отображения на фронтенде.
     *
     * @param \App\Models\Entry $entry Запись
     * @return void
     */
    private function sanitizeRichTextFields(Entry $entry): void
    {
        $data = $entry->data_json ?? [];

        // Санитизируем body_html
        if (isset($data['body_html']) && is_string($data['body_html'])) {
            $data['body_html_sanitized'] = $this->sanitizer->sanitize($data['body_html']);
        }

        // Санитизируем excerpt_html
        if (isset($data['excerpt_html']) && is_string($data['excerpt_html'])) {
            $data['excerpt_html_sanitized'] = $this->sanitizer->sanitize($data['excerpt_html']);
        }

        $entry->data_json = $data;
    }
}

