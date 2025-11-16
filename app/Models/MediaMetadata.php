<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Eloquent модель для нормализованных AV-метаданных медиа (MediaMetadata).
 *
 * Хранит технические характеристики аудио/видео:
 * длительность, битрейт, частоту кадров, количество кадров и кодеки.
 *
 * @property string $id ULID идентификатор
 * @property string $media_id Идентификатор связанного медиа-файла
 * @property int|null $duration_ms Длительность медиа в миллисекундах
 * @property int|null $bitrate_kbps Битрейт в килобитах в секунду
 * @property float|null $frame_rate Частота кадров
 * @property int|null $frame_count Количество кадров
 * @property string|null $video_codec Видео кодек
 * @property string|null $audio_codec Аудио кодек
 *
 * @property-read \App\Models\Media $media Связанный медиа-файл
 */
class MediaMetadata extends Model
{
    use HasFactory;

    /**
     * Все поля доступны для массового присвоения.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

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
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'duration_ms' => 'integer',
        'bitrate_kbps' => 'integer',
        'frame_rate' => 'float',
        'frame_count' => 'integer',
    ];

    /**
     * Установить ULID перед созданием модели, если он не задан.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(static function (MediaMetadata $model): void {
            if ($model->getKey() === null) {
                $model->setAttribute($model->getKeyName(), (string) Str::ulid());
            }
        });
    }

    /**
     * Связанный медиа-файл.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Media, \App\Models\MediaMetadata>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}


