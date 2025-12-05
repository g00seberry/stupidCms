<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Индексированное скалярное значение из Entry.data_json.
 *
 * Использует уникальный индекс (entry_id, path_id, array_index) для обеспечения уникальности.
 * array_index может быть NULL для cardinality=one, поэтому используется уникальный индекс вместо первичного ключа.
 *
 * @property int $id
 * @property int $entry_id
 * @property int $path_id
 * @property int|null $array_index Индекс в массиве (NULL для cardinality=one, обязателен для many)
 * @property string|null $value_string
 * @property int|null $value_int
 * @property float|null $value_float
 * @property bool|null $value_bool
 * @property \Illuminate\Support\Carbon|null $value_datetime Используется для date и datetime типов
 * @property string|null $value_text
 * @property array|null $value_json
 *
 * @property-read \App\Models\Entry $entry
 * @property-read \App\Models\Path $path
 */
class DocValue extends Model
{
    /**
     * Отключить автоматическое управление timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'entry_id',
        'path_id',
        'array_index',
        'value_string',
        'value_int',
        'value_float',
        'value_bool',
        'value_datetime',
        'value_text',
        'value_json',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'value_bool' => 'boolean',
        'value_json' => 'array',
        'value_datetime' => 'datetime',
    ];

    /**
     * Связь с Entry.
     *
     * @return BelongsTo<Entry, DocValue>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * Связь с Path.
     *
     * @return BelongsTo<Path, DocValue>
     */
    public function path(): BelongsTo
    {
        return $this->belongsTo(Path::class);
    }
}
