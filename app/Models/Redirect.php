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
 * @property string $from_path Исходный путь (откуда перенаправлять)
 * @property string $to_path Целевой путь (куда перенаправлять)
 * @property int $code HTTP код редиректа (301, 302 и т.д.)
 * @property int $hit_count Количество переходов по редиректу
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

