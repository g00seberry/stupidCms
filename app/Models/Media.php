<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Media\MediaKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Eloquent модель для медиа-файлов (Media).
 *
 * Представляет загруженные файлы: изображения, видео, аудио, документы.
 * Использует ULID в качестве первичного ключа. Поддерживает мягкое удаление.
 * Уникальность обеспечивается по комбинации (disk, path).
 * Специфичные метаданные хранятся в связанных таблицах:
 * - MediaImage для изображений (width, height, exif_json)
 * - MediaAvMetadata для видео/аудио (duration_ms, bitrate, codecs и т.д.)
 *
 * @property string $id ULID идентификатор
 * @property string $disk Диск хранения
 * @property string $path Путь к файлу в хранилище (уникален в рамках disk)
 * @property string $original_name Оригинальное имя файла
 * @property string|null $ext Расширение файла
 * @property string $mime MIME-тип файла
 * @property int $size_bytes Размер файла в байтах
 * @property string|null $checksum_sha256 SHA256 checksum файла (индексирован)
 * @property string|null $title Заголовок медиа
 * @property string|null $alt Альтернативный текст
 * @property string|null $collection Коллекция/группа медиа
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at Дата мягкого удаления
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MediaVariant> $variants Варианты файла (превью, миниатюры)
 * @property-read \App\Models\MediaImage|null $image Метаданные изображения (только для изображений)
 * @property-read \App\Models\MediaAvMetadata|null $avMetadata Нормализованные AV-метаданные (только для видео/аудио)
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
        'deleted_at' => 'datetime',
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
     * Метаданные изображения (один-к-одному).
     *
     * Связь с таблицей media_images, содержащей специфичные метаданные для изображений:
     * width, height, exif_json.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\MediaImage, \App\Models\Media>
     */
    public function image(): HasOne
    {
        return $this->hasOne(MediaImage::class);
    }

    /**
     * Нормализованные AV-метаданные (один-к-одному).
     *
     * Связь с таблицей media_av_metadata, содержащей технические характеристики
     * аудио/видео: длительность, битрейт, частоту кадров, кодеки.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\MediaAvMetadata, \App\Models\Media>
     */
    public function avMetadata(): HasOne
    {
        return $this->hasOne(MediaAvMetadata::class);
    }

    /**
     * Определить тип медиа-файла по MIME-типу.
     *
     * Возвращает MediaKind enum в зависимости от MIME-типа.
     *
     * @return \App\Domain\Media\MediaKind Тип медиа-файла
     */
    public function kind(): MediaKind
    {
        return MediaKind::fromMime($this->mime);
    }
}

