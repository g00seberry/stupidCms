<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot модель для связи записей и медиа-файлов (EntryMedia).
 *
 * Представляет связь many-to-many между Entry и Media с дополнительными полями:
 * field_key (ключ поля в структуре контента) и order (порядок сортировки).
 *
 * @property int $entry_id ID записи
 * @property int $media_id ID медиа-файла
 * @property string|null $field_key Ключ поля в структуре контента (например, 'hero_image')
 * @property int|null $order Порядок сортировки медиа в рамках записи
 *
 * @property-read \App\Models\Entry $entry Запись
 * @property-read \App\Models\Media $media Медиа-файл
 */
class EntryMedia extends Pivot
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
    protected $table = 'entry_media';

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
        'order' => 'integer',
    ];
}

