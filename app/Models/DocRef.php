<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель DocRef — индексированная ссылка Entry → Entry.
 *
 * @property int $entry_id
 * @property int $path_id
 * @property int $idx
 * @property int $target_entry_id
 * @property \Illuminate\Support\Carbon|null $created_at
 *
 * @property-read \App\Models\Entry $owner
 * @property-read \App\Models\Entry $target
 * @property-read \App\Models\Path $path
 */
class DocRef extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entry_id',
        'path_id',
        'idx',
        'target_entry_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Связи

    /**
     * Владелец ссылки (Entry, от которого идет ссылка).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'entry_id');
    }

    /**
     * Целевой Entry (на который ссылаются).
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'target_entry_id');
    }

    /**
     * Связь с Path.
     */
    public function path(): BelongsTo
    {
        return $this->belongsTo(Path::class);
    }
}

