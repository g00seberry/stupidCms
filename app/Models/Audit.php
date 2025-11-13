<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для аудита изменений (Audit).
 *
 * Хранит историю изменений сущностей системы для аудита и отслеживания действий пользователей.
 *
 * @property int $id
 * @property int|null $user_id ID пользователя, выполнившего действие
 * @property string $event Тип события (created, updated, deleted и т.д.)
 * @property string $auditable_type Тип изменяемой сущности (класс модели)
 * @property int|string $auditable_id ID изменяемой сущности
 * @property array|null $diff_json Различия между старым и новым состоянием
 * @property array|null $meta Дополнительные метаданные события
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\User|null $user Пользователь, выполнивший действие
 */
class Audit extends Model
{
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
        'diff_json' => 'array',
        'meta' => 'array',
    ];

    /**
     * Связь с пользователем, выполнившим действие.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\Audit>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

