<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FormConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent модель для конфигурации формы компонентов (FormConfig).
 *
 * Хранит конфигурацию формы компонентов для конкретной пары PostType (id) + Blueprint.
 * Конфигурация представляет собой JSON объект, где ключи - это full_path из Path,
 * а значения - EditComponent (конфигурация компонента редактирования).
 *
 * @property int $id
 * @property int $post_type_id ID типа контента
 * @property int $blueprint_id ID blueprint
 * @property array<string, mixed> $config_json JSON с конфигурацией компонентов (ключ - full_path, значение - EditComponent)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\PostType $postType PostType
 * @property-read \App\Models\Blueprint $blueprint Blueprint
 */
class FormConfig extends Model
{
    use HasFactory;

    /**
     * Mass-assignable fields.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'post_type_id',
        'blueprint_id',
        'config_json',
    ];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'config_json' => 'array',
    ];

    /**
     * Связь с PostType.
     *
     * @return BelongsTo<PostType, FormConfig>
     */
    public function postType(): BelongsTo
    {
        return $this->belongsTo(PostType::class);
    }

    /**
     * Связь с blueprint.
     *
     * @return BelongsTo<Blueprint, FormConfig>
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\FormConfigFactory
     */
    protected static function newFactory(): FormConfigFactory
    {
        return FormConfigFactory::new();
    }
}
