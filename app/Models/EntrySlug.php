<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для истории slug'ов записей (EntrySlug).
 *
 * Хранит историю изменений slug'ов для каждой записи Entry.
 * Позволяет отслеживать все предыдущие URL и обеспечивает редиректы.
 *
 * @property int $entry_id ID записи
 * @property string $slug Slug записи
 * @property bool $is_current Флаг текущего slug (только один true на запись)
 * @property \Illuminate\Support\Carbon $created_at Дата создания записи в истории
 *
 * @property-read \App\Models\Entry $entry Запись, к которой относится slug
 */
class EntrySlug extends Model
{
    /**
     * Отключить автоматическое управление timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Все поля доступны для массового присвоения.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Имя таблицы.
     *
     * @var string
     */
    protected $table = 'entry_slugs';

    /**
     * Отключить автоинкремент ID (составной первичный ключ).
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Отсутствует первичный ключ (составной ключ через entry_id + slug).
     *
     * @var string|null
     */
    protected $primaryKey = null;

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_current' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Связь с записью Entry.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Entry, \App\Models\EntrySlug>
     */
    public function entry()
    {
        return $this->belongsTo(Entry::class);
    }
}

