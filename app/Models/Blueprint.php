<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Database\Factories\BlueprintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Модель Blueprint — схема полей для Entry.
 *
 * @property int $id
 * @property int|null $post_type_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string $type 'full' или 'component'
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\PostType|null $postType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $paths
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $ownPaths
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $materializedPaths
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Entry> $entries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Blueprint> $components
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Blueprint> $usedInBlueprints
 */
class Blueprint extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'post_type_id',
        'slug',
        'name',
        'description',
        'type',
        'is_default',
    ];

    /**
     * Значения атрибутов по умолчанию.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // Связи

    /**
     * Связь с PostType.
     */
    public function postType(): BelongsTo
    {
        return $this->belongsTo(PostType::class);
    }

    /**
     * Все Paths (собственные + материализованные).
     */
    public function paths(): HasMany
    {
        return $this->hasMany(Path::class);
    }

    /**
     * Только собственные Paths (без материализованных).
     */
    public function ownPaths(): HasMany
    {
        return $this->hasMany(Path::class)
            ->whereNull('source_component_id');
    }

    /**
     * Только материализованные Paths.
     */
    public function materializedPaths(): HasMany
    {
        return $this->hasMany(Path::class)
            ->whereNotNull('source_component_id');
    }

    /**
     * Связь с Entry.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * Компоненты, используемые в этом Blueprint.
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(
            Blueprint::class,
            'blueprint_components',
            'blueprint_id',
            'component_id'
        )->withPivot('path_prefix')
         ->withTimestamps();
    }

    /**
     * Blueprint'ы, в которых этот компонент используется.
     */
    public function usedInBlueprints(): BelongsToMany
    {
        return $this->belongsToMany(
            Blueprint::class,
            'blueprint_components',
            'component_id',
            'blueprint_id'
        )->withPivot('path_prefix')
         ->withTimestamps();
    }

    // Скоупы

    /**
     * Скоуп: только full Blueprint.
     */
    public function scopeFull($query)
    {
        return $query->where('type', 'full');
    }

    /**
     * Скоуп: только component Blueprint.
     */
    public function scopeComponent($query)
    {
        return $query->where('type', 'component');
    }

    /**
     * Скоуп: только default Blueprint.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Скоуп: для конкретного PostType.
     */
    public function scopeForPostType($query, int $postTypeId)
    {
        return $query->where('post_type_id', $postTypeId);
    }

    // Методы

    /**
     * Является ли компонентом.
     */
    public function isComponent(): bool
    {
        return $this->type === 'component';
    }

    /**
     * Получить все Paths (собственные + материализованные).
     * Кешируется на 1 час.
     *
     * @param bool $cached Использовать кеш
     * @return \Illuminate\Support\Collection<int, \App\Models\Path>
     */
    public function getAllPaths(bool $cached = true): Collection
    {
        if (!$cached) {
            return $this->paths;
        }

        $cacheKey = "blueprint:{$this->id}:all_paths";

        return Cache::remember($cacheKey, 3600, function () {
            return $this->paths()->get();
        });
    }

    /**
     * Найти Path по full_path.
     */
    public function getPathByFullPath(string $fullPath): ?Path
    {
        return $this->getAllPaths()->firstWhere('full_path', $fullPath);
    }

    /**
     * Материализовать Paths из компонента.
     *
     * @param \App\Models\Blueprint $component
     * @param string $pathPrefix
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function materializeComponentPaths(Blueprint $component, string $pathPrefix): void
    {
        if ($component->type !== 'component') {
            throw new \InvalidArgumentException('Можно материализовать только component Blueprint');
        }

        if (empty($pathPrefix)) {
            throw new \InvalidArgumentException('path_prefix обязателен');
        }

        $this->validateNoPathConflicts($component, $pathPrefix);

        DB::transaction(function () use ($component, $pathPrefix) {
            foreach ($component->ownPaths as $sourcePath) {
                if ($sourcePath->parent_id !== null) {
                    throw new \LogicException(
                        "Path '{$sourcePath->full_path}' в компоненте имеет parent_id. " .
                        "Вложенные Paths в компонентах не поддерживаются."
                    );
                }

                Path::create([
                    'blueprint_id' => $this->id,
                    'source_component_id' => $component->id,
                    'source_path_id' => $sourcePath->id,
                    'parent_id' => null,
                    'name' => $sourcePath->name,
                    'full_path' => $pathPrefix . '.' . $sourcePath->full_path,
                    'data_type' => $sourcePath->data_type,
                    'cardinality' => $sourcePath->cardinality,
                    'is_indexed' => $sourcePath->is_indexed,
                    'is_required' => $sourcePath->is_required,
                    'ref_target_type' => $sourcePath->ref_target_type,
                    'validation_rules' => $sourcePath->validation_rules,
                    'ui_options' => $sourcePath->ui_options,
                ]);
            }
        });

        $this->invalidatePathsCache();
    }

    /**
     * Удалить материализованные Paths компонента.
     *
     * @param \App\Models\Blueprint $component
     */
    public function dematerializeComponentPaths(Blueprint $component): void
    {
        Path::where('blueprint_id', $this->id)
            ->where('source_component_id', $component->id)
            ->delete();

        $this->invalidatePathsCache();
    }

    /**
     * Инвалидировать кеш Paths.
     */
    public function invalidatePathsCache(): void
    {
        Cache::forget("blueprint:{$this->id}:all_paths");
    }

    /**
     * Проверить конфликты full_path.
     *
     * @param \App\Models\Blueprint $component
     * @param string $pathPrefix
     * @throws \LogicException
     */
    private function validateNoPathConflicts(Blueprint $component, string $pathPrefix): void
    {
        $existingPaths = $this->paths()->pluck('full_path');

        foreach ($component->ownPaths as $sourcePath) {
            $newFullPath = $pathPrefix . '.' . $sourcePath->full_path;

            if ($existingPaths->contains($newFullPath)) {
                throw new \LogicException(
                    "Конфликт: Path '{$newFullPath}' уже существует в Blueprint '{$this->slug}'"
                );
            }
        }
    }

    /**
     * Фабрика модели.
     */
    protected static function newFactory(): BlueprintFactory
    {
        return BlueprintFactory::new();
    }
}

