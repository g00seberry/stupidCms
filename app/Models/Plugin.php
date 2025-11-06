<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $guarded = [];
    protected $casts = ['manifest_json' => 'array', 'enabled' => 'boolean'];

    public function migrations()
    {
        return $this->hasMany(PluginMigration::class);
    }

    public function reserved()
    {
        return $this->hasMany(PluginReserved::class);
    }
}

