<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PluginMigration extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'plugin_migrations';

    public function plugin()
    {
        return $this->belongsTo(Plugin::class);
    }
}

