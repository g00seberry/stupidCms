<?php

namespace App\Models;

use Database\Factories\TaxonomyFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Taxonomy extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'options_json' => 'array',
        'hierarchical' => 'boolean',
    ];

    public function terms()
    {
        return $this->hasMany(Term::class);
    }

    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['name'] ?? null,
            set: fn ($value) => ['name' => $value],
        );
    }

    /**
     * @return \Database\Factories\TaxonomyFactory
     */
    protected static function newFactory(): TaxonomyFactory
    {
        return TaxonomyFactory::new();
    }
}

