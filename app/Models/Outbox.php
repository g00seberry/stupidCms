<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для исходящих сообщений (Outbox).
 *
 * Хранит задачи/сообщения для асинхронной обработки с поддержкой повторных попыток.
 * Используется для реализации паттерна Outbox для гарантированной доставки.
 *
 * @property int $id
 * @property string $type Тип задачи/сообщения
 * @property array $payload_json Данные задачи (JSON)
 * @property int $attempts Количество попыток обработки
 * @property \Illuminate\Support\Carbon $available_at Дата, когда задача становится доступной для обработки
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Outbox extends Model
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
        'payload_json' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
    ];
}

