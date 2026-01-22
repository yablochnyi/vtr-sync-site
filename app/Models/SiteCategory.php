<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteCategory extends Model
{
    protected $guarded = false;

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function posts()
    {
        return $this->belongsToMany(SitePost::class, 'site_post_category');
    }

}
