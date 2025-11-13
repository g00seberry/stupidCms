<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Eloquent модель для вариантов медиа-файлов (MediaVariant).
 *
 * Представляет производные версии медиа-файла: превью, миниатюры, ресайзы изображений.
 * Использует ULID в качестве первичного ключа.
 *
 * @property string $id ULID идентификатор
 * @property string $media_id ID исходного медиа-файла
 * @property string $name Название варианта (например, 'thumbnail', 'preview', 'large')
 * @property string $path Путь к файлу варианта в хранилище
 * @property int $width Ширина (для изображений)
 * @property int $height Высота (для изображений)
 * @property int $size Размер файла в байтах
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Media $media Исходный медиа-файл
 */
class MediaVariant extends Model
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
    protected $table = 'media_variants';

    /**
     * Связь с исходным медиа-файлом.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Media, \App\Models\MediaVariant>
     */
    public function media()
    {
        return $this->belongsTo(Media::class);
    }
}

