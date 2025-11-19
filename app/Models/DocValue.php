<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель DocValue — индексированное скалярное значение.
 *
 * @property int $entry_id
 * @property int $path_id
 * @property int $idx
 * @property string|null $value_string
 * @property int|null $value_int
 * @property float|null $value_float
 * @property bool|null $value_bool
 * @property string|null $value_text
 * @property array|null $value_json
 * @property \Illuminate\Support\Carbon|null $created_at
 *
 * @property-read \App\Models\Entry $entry
 * @property-read \App\Models\Path $path
 */
class DocValue extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entry_id',
        'path_id',
        'idx',
        'value_string',
        'value_int',
        'value_float',
        'value_bool',
        'value_text',
        'value_json',
    ];

    protected $casts = [
        'value_json' => 'array',
        'value_bool' => 'boolean',
        'created_at' => 'datetime',
    ];

    // Связи

    /**
     * Связь с Entry.
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * Связь с Path.
     */
    public function path(): BelongsTo
    {
        return $this->belongsTo(Path::class);
    }

    /**
     * Получить значение из нужного value_* поля.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return match($this->path->data_type) {
            'string' => $this->value_string,
            'int' => $this->value_int,
            'float' => $this->value_float,
            'bool' => $this->value_bool,
            'text' => $this->value_text,
            'json' => $this->value_json,
            default => null,
        };
    }
}

