<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use Database\Factories\RouteNodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent модель для узлов маршрутов (RouteNode).
 *
 * Представляет узел в иерархическом дереве маршрутов для DB-driven роутинга.
 * Поддерживает группы маршрутов и конкретные маршруты с различными типами действий.
 *
 * @property int $id
 * @property int|null $parent_id ID родительского узла (NULL для корневых)
 * @property int $sort_order Порядок сортировки среди детей одного родителя
 * @property bool $enabled Включён ли узел (регистрируется ли маршрут)
 * @property bool $readonly Защита от изменения (true для декларативных маршрутов)
 * @property \App\Enums\RouteNodeKind $kind Тип узла: GROUP или ROUTE
 * @property string|null $name Имя маршрута (Route::name())
 * @property string|null $domain Домен для маршрута
 * @property string|null $prefix Префикс URI для группы
 * @property string|null $namespace Namespace контроллеров для группы
 * @property array|null $methods HTTP методы для маршрута (только для kind='route')
 * @property string|null $uri URI паттерн маршрута (только для kind='route')
 * @property \App\Enums\RouteNodeActionType $action_type Тип действия: CONTROLLER или ENTRY
 * @property string|null $action Действие (Controller@method, view:..., redirect:...)
 * @property int|null $entry_id ID связанной Entry (для action_type='entry')
 * @property array|null $middleware Массив middleware
 * @property array|null $where Ограничения параметров маршрута
 * @property array|null $defaults Значения по умолчанию для параметров
 * @property array|null $options Дополнительные опции
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at Дата мягкого удаления
 *
 * @property-read \App\Models\RouteNode|null $parent Родительский узел
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode> $children Дочерние узлы
 * @property-read \App\Models\Entry|null $entry Связанная Entry (для action_type='entry')
 */
class RouteNode extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Поля, доступные для массового присвоения.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'sort_order',
        'enabled',
        'readonly',
        'kind',
        'name',
        'domain',
        'prefix',
        'namespace',
        'methods',
        'uri',
        'action_type',
        'action',
        'entry_id',
        'middleware',
        'where',
        'defaults',
        'options',
    ];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'readonly' => 'boolean',
        'kind' => RouteNodeKind::class,
        'action_type' => RouteNodeActionType::class,
        'methods' => 'array',
        'middleware' => 'array',
        'where' => 'array',
        'defaults' => 'array',
        'options' => 'array',
    ];

    /**
     * Связь с родительским узлом.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\RouteNode, \App\Models\RouteNode>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(RouteNode::class, 'parent_id');
    }

    /**
     * Связь с дочерними узлами.
     *
     * Возвращает отсортированные по sort_order, затем по id дочерние узлы.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\RouteNode>
     */
    public function children(): HasMany
    {
        return $this->hasMany(RouteNode::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Связь с Entry (для action_type='entry').
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Entry, \App\Models\RouteNode>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'entry_id');
    }

    /**
     * Скоуп: только включённые узлы.
     *
     * Фильтрует узлы, у которых enabled = true.
     *
     * @param \Illuminate\Database\Eloquent\Builder<RouteNode> $query
     * @return \Illuminate\Database\Eloquent\Builder<RouteNode>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Скоуп: узлы определённого типа.
     *
     * Фильтрует узлы по типу (GROUP или ROUTE).
     *
     * @param \Illuminate\Database\Eloquent\Builder<RouteNode> $query
     * @param \App\Enums\RouteNodeKind $kind Тип узла
     * @return \Illuminate\Database\Eloquent\Builder<RouteNode>
     */
    public function scopeOfKind(Builder $query, RouteNodeKind $kind): Builder
    {
        return $query->where('kind', $kind->value);
    }

    /**
     * Скоуп: только корневые узлы.
     *
     * Фильтрует узлы, у которых parent_id IS NULL.
     *
     * @param \Illuminate\Database\Eloquent\Builder<RouteNode> $query
     * @return \Illuminate\Database\Eloquent\Builder<RouteNode>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\RouteNodeFactory
     */
    protected static function newFactory(): RouteNodeFactory
    {
        return RouteNodeFactory::new();
    }
}

