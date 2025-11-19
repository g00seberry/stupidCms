<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent модель для метаданных изображений (MediaImage).
 *
 * Хранит специфичные метаданные для изображений:
 * размеры (width, height) и EXIF метаданные.
 * Связана с Media через отношение один-к-одному.
 *
 * @property string $id ULID идентификатор
 * @property string $media_id Идентификатор связанного медиа-файла (уникален)
 * @property int $width Ширина изображения в пикселях
 * @property int $height Высота изображения в пикселях
 * @property array|null $exif_json EXIF метаданные изображения (JSON/JSONB)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Media $media Связанный медиа-файл
 */
class MediaImage extends Model
{
    use HasFactory;
    use HasUlids;

    /**
     * Тип первичного ключа (ULID строка).
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Отключить автоинкремент (используется ULID).
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Все поля доступны для массового присвоения.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Имя таблицы.
     *
     * @var string
     */
    protected $table = 'media_images';

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'exif_json' => 'array',
    ];

    /**
     * Связь с медиа-файлом (один-к-одному).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Media, \App\Models\MediaImage>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}

