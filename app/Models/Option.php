<?php

namespace App\Models;

use App\Casts\AsJsonValue;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Option extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'options';

    protected $fillable = [
        'namespace',
        'key',
        'value_json',
        'description',
    ];

    protected $casts = [
        'value_json' => AsJsonValue::class,
    ];

    protected $keyType = 'string';

    public $incrementing = false;
}
