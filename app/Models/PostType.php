<?php

namespace App\Models;

use Database\Factories\PostTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostType extends Model
{
    use HasFactory;

    /**
     * Mass-assignable fields.
     * 
     * Note: While all fields are fillable for factory/seeder convenience,
     * the Admin API only updates options_json (enforced by controller logic).
     */
    protected $fillable = [
        'slug',
        'name',
        'template',
        'options_json',
    ];

    protected $casts = [
        'options_json' => 'array',
    ];

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PostTypeFactory
    {
        return PostTypeFactory::new();
    }
}

