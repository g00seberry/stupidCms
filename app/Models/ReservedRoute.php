<?php

namespace App\Models;

use App\Domain\Routing\PathNormalizer;
use App\Domain\Routing\Exceptions\InvalidPathException;
use Illuminate\Database\Eloquent\Model;

class ReservedRoute extends Model
{
    protected $fillable = [
        'path',
        'kind',
        'source',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Мутатор для автоматической нормализации пути при установке.
     * Защищает от прямого создания модели без нормализации.
     *
     * @throws InvalidPathException
     */
    public function setPathAttribute(string $value): void
    {
        $this->attributes['path'] = PathNormalizer::normalize($value);
    }
}

