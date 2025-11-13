<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для closure-table иерархии термов (TermTree).
 *
 * Реализует closure-table паттерн для хранения иерархических связей между термами.
 * Позволяет эффективно получать всех предков и потомков терма.
 *
 * @property int $ancestor_id ID терма-предка
 * @property int $descendant_id ID терма-потомка
 * @property int $depth Глубина связи (1 = прямой родитель/потомок, 2+ = дальние связи)
 */
class TermTree extends Model
{
    /**
     * Отключить автоматическое управление timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Имя таблицы.
     *
     * @var string
     */
    protected $table = 'term_tree';

    /**
     * Все поля доступны для массового присвоения.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Отключить автоинкремент (составной первичный ключ).
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Отсутствует первичный ключ (составной ключ через ancestor_id + descendant_id).
     *
     * @var string|null
     */
    protected $primaryKey = null;
}

