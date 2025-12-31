<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PathRefConstraintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent модель для ограничений ref-полей (PathRefConstraint).
 *
 * Представляет ограничение на допустимые PostType для ref-поля в Path.
 * Хранит связь между Path и PostType, указывая, какие типы записей
 * могут быть использованы в качестве значения для данного ref-поля.
 *
 * @property int $id
 * @property int $path_id ID пути (path)
 * @property int $allowed_post_type_id ID допустимого типа записи
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Path $path Path, к которому относится ограничение
 * @property-read \App\Models\PostType $allowedPostType Допустимый тип записи
 */
class PathRefConstraint extends Model
{
    use HasFactory;

    /**
     * Массово заполняемые поля.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'path_id',
        'allowed_post_type_id',
    ];

    /**
     * Связь с Path, к которому относится ограничение.
     *
     * @return BelongsTo<Path, PathRefConstraint>
     */
    public function path(): BelongsTo
    {
        return $this->belongsTo(Path::class);
    }

    /**
     * Связь с PostType, который допустим для этого ref-поля.
     *
     * @return BelongsTo<PostType, PathRefConstraint>
     */
    public function allowedPostType(): BelongsTo
    {
        return $this->belongsTo(PostType::class, 'allowed_post_type_id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return PathRefConstraintFactory
     */
    protected static function newFactory(): PathRefConstraintFactory
    {
        return PathRefConstraintFactory::new();
    }
}

