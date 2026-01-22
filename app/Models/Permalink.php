<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permalink extends Model
{
    protected $guarded = false;

    protected $casts = [
        'open_in_new_tab' => 'bool',
    ];
}
