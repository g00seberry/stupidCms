<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Индексированное скалярное значение из Entry.data_json.
 *
 * @property int $id
 * @property int $entry_id
 * @property int $path_id
 * @property string $cardinality Денормализованное значение из paths.cardinality: 'one' | 'many'
 * @property int|null $array_index Индекс в массиве (NULL для cardinality=one, обязателен для many)
 * @property string|null $value_string
 * @property int|null $value_int
 * @property float|null $value_float
 * @property bool|null $value_bool
 * @property string|null $value_date
 * @property string|null $value_datetime
 * @property string|null $value_text
 * @property array|null $value_json
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Entry $entry
 * @property-read \App\Models\Path $path
 */
class DocValue extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'entry_id',
        'path_id',
        'cardinality',
        'array_index',
        'value_string',
        'value_int',
        'value_float',
        'value_bool',
        'value_date',
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
