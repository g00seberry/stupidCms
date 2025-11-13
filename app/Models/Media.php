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
 * @property string $filename Имя файла
 * @property string $path Путь к файлу в хранилище
 * @property string $mime MIME-тип файла
 * @property int $size Размер файла в байтах
 * @property array|null $exif_json EXIF метаданные (для изображений)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at Дата мягкого удаления
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MediaVariant> $variants Варианты файла (превью, миниатюры)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Entry> $entries Записи, использующие этот медиа-файл
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
     * Связь с записями, использующими этот медиа-файл.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Entry, \App\Models\Media>
     */
    public function entries()
    {
        return $this->belongsToMany(Entry::class, 'entry_media', 'media_id', 'entry_id')
            ->using(EntryMedia::class)
            ->withPivot(['field_key', 'order']);
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

