<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Индексированная ссылка на другой Entry.
 *
 * @property int $id
 * @property int $entry_id
 * @property int $path_id
 * @property int|null $array_index
 * @property int $target_entry_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Entry $entry
 * @property-read \App\Models\Path $path
 * @property-read \App\Models\Entry $targetEntry
 */
class DocRef extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'entry_id',
        'path_id',
        'array_index',
        'target_entry_id',
    ];

    /**
     * Связь с Entry (источник ссылки).
     *
     * @return BelongsTo<Entry, DocRef>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * Связь с Path.
     *
     * @return BelongsTo<Path, DocRef>
     */
    public function path(): BelongsTo
    {
        return $this->belongsTo(Path::class);
    }

    /**
     * Связь с целевым Entry (куда ссылается).
     *
     * @return BelongsTo<Entry, DocRef>
     */
    public function targetEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'target_entry_id');
    }
}
