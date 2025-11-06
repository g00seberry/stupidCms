<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    protected $guarded = [];
    protected $casts = ['value_json' => 'array'];

    public static function get(string $ns, string $key, $default = null)
    {
        $row = static::query()
            ->where('namespace', $ns)->where('key', $key)->value('value_json');
        return $row ?? $default;
    }

    public static function set(string $ns, string $key, $value): void
    {
        static::query()->updateOrCreate(
            ['namespace' => $ns, 'key' => $key],
            ['value_json' => $value]
        );
    }
}

