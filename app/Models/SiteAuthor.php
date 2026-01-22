<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAuthor extends Model
{
    protected $guarded = false;

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function posts()
    {
        return $this->hasMany(SitePost::class, 'author_id');
    }
}
