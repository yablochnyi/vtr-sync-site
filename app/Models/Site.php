<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $guarded = false;

    public function posts()
    {
        return $this->hasMany(SitePost::class);
    }

    public function categories()
    {
        return $this->hasMany(\App\Models\SiteCategory::class);
    }

    public function authors()
    {
        return $this->hasMany(\App\Models\SiteAuthor::class);
    }
}
