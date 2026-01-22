<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSettings extends Model
{
    protected $guarded = false;

    protected $casts = [
        'settings' => 'array',
    ];
}
