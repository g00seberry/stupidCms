<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Шаблон структуры данных для Entry.
 *
 * @property int $id
 * @property string $name Название blueprint
 * @property string $code Уникальный код
 * @property string|null $description Описание
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $paths
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BlueprintEmbed> $embeds
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BlueprintEmbed> $embeddedIn
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PostType> $postTypes
 */
class Blueprint extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    /**
     * Поля blueprint'а (собственные + материализованные).
     *
     * @return HasMany<Path>
     */
    public function paths(): HasMany
    {
        return $this->hasMany(Path::class);
    }

    /**
     * Встраивания этого blueprint в другие (где данный blueprint = host).
     *
     * @return HasMany<BlueprintEmbed>
     */
    public function embeds(): HasMany
    {
        return $this->hasMany(BlueprintEmbed::class, 'blueprint_id');
    }

    /**
     * Где этот blueprint встроен в другие (где данный blueprint = embedded).
     *
     * @return HasMany<BlueprintEmbed>
     */
    public function embeddedIn(): HasMany
    {
        return $this->hasMany(BlueprintEmbed::class, 'embedded_blueprint_id');
    }

    /**
     * PostType, использующие этот blueprint.
     *
     * @return HasMany<PostType>
     */
    public function postTypes(): HasMany
    {
        return $this->hasMany(PostType::class);
    }
}
