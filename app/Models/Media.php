<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Eloquent модель для медиа-файлов (Media).
 *
 * Представляет загруженные файлы: изображения, видео, аудио, документы.
 * Использует ULID в качестве первичного ключа. Поддерживает мягкое удаление.
 *
 * @property string $id ULID идентификатор
 * @property string $path Путь к файлу в хранилище
 * @property string $disk Диск хранения
 * @property string $original_name Оригинальное имя файла
 * @property string|null $ext Расширение файла
 * @property string $mime MIME-тип файла
 * @property int $size_bytes Размер файла в байтах
 * @property int|null $width Ширина изображения в пикселях
 * @property int|null $height Высота изображения в пикселях
 * @property int|null $duration_ms Длительность медиа в миллисекундах
 * @property string|null $checksum_sha256 SHA256 checksum файла
 * @property array|null $exif_json EXIF метаданные (для изображений)
 * @property string|null $title Заголовок медиа
 * @property string|null $alt Альтернативный текст
 * @property string|null $collection Коллекция/группа медиа
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at Дата мягкого удаления
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MediaVariant> $variants Варианты файла (превью, миниатюры)
 * @property-read \App\Models\MediaMetadata|null $metadata Нормализованные AV-метаданные
 */
class Media extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

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
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'exif_json' => 'array',
        'deleted_at' => 'datetime',
        'width' => 'integer',
        'height' => 'integer',
        'duration_ms' => 'integer',
        'size_bytes' => 'integer',
    ];

    /**
     * Связь с вариантами медиа-файла (превью, миниатюры, ресайзы).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\MediaVariant, \App\Models\Media>
     */
    public function variants()
    {
        return $this->hasMany(MediaVariant::class);
    }

    /**
     * Нормализованные AV-метаданные (один-к-одному).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\MediaMetadata, \App\Models\Media>
     */
    public function metadata()
    {
        return $this->hasOne(MediaMetadata::class);
    }


    /**
     * Определить тип медиа-файла по MIME-типу.
     *
     * Возвращает 'image', 'video', 'audio' или 'document' в зависимости от MIME-типа.
     *
     * @return string Тип медиа-файла: 'image', 'video', 'audio' или 'document'
     */
    public function kind(): string
    {
        return match (true) {
            str_starts_with($this->mime, 'image/') => 'image',
            str_starts_with($this->mime, 'video/') => 'video',
            str_starts_with($this->mime, 'audio/') => 'audio',
            default => 'document',
        };
    }
}

