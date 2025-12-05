<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Связь встраивания blueprint'а.
 *
 * @property int $id
 * @property int $blueprint_id Кто встраивает (host)
 * @property int $embedded_blueprint_id Кого встраивают
 * @property int|null $host_path_id Под каким полем (NULL = корень)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Blueprint $blueprint
 * @property-read \App\Models\Blueprint $embeddedBlueprint
 * @property-read \App\Models\Path|null $hostPath
 */
class BlueprintEmbed extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'blueprint_id',
        'embedded_blueprint_id',
        'host_path_id',
    ];

    /**
     * Host blueprint (кто встраивает).
     *
     * @return BelongsTo<Blueprint, BlueprintEmbed>
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Embedded blueprint (кого встраивают).
     *
     * @return BelongsTo<Blueprint, BlueprintEmbed>
     */
    public function embeddedBlueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class, 'embedded_blueprint_id');
    }

    /**
     * Поле-контейнер (под которым живёт embedded).
     *
     * @return BelongsTo<Path, BlueprintEmbed>
     */
    public function hostPath(): BelongsTo
    {
        return $this->belongsTo(Path::class, 'host_path_id');
    }
}
