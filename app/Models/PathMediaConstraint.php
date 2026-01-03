<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PathMediaConstraintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent модель для ограничений media-полей (PathMediaConstraint).
 *
 * Представляет ограничение на допустимые MIME-типы для media-поля в Path.
 * Хранит связь между Path и MIME-типом, указывая, какие типы медиа-файлов
 * могут быть использованы в качестве значения для данного media-поля.
 *
 * @property int $id
 * @property int $path_id ID пути (path)
 * @property string $allowed_mime Допустимый MIME-тип (например, 'image/jpeg')
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Path $path Path, к которому относится ограничение
 */
class PathMediaConstraint extends Model
{
    use HasFactory;

    /**
     * Массово заполняемые поля.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'path_id',
        'allowed_mime',
    ];

    /**
     * Связь с Path, к которому относится ограничение.
     *
     * @return BelongsTo<Path, PathMediaConstraint>
     */
    public function path(): BelongsTo
    {
        return $this->belongsTo(Path::class);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return PathMediaConstraintFactory
     */
    protected static function newFactory(): PathMediaConstraintFactory
    {
        return PathMediaConstraintFactory::new();
    }
}

