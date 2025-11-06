<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PluginReserved extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'plugin_reserved';

    public function plugin()
    {
        return $this->belongsTo(Plugin::class);
    }
}

