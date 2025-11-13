<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для редиректов (Redirect).
 *
 * Хранит правила перенаправления URL (301, 302 и т.д.).
 *
 * @property int $id
 * @property string $from Исходный URL (откуда перенаправлять)
 * @property string $to Целевой URL (куда перенаправлять)
 * @property int $status HTTP статус редиректа (301, 302 и т.д.)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Redirect extends Model
{
    /**
     * Все поля доступны для массового присвоения.
     *
     * @var array<int, string>
     */
    protected $guarded = [];
}

