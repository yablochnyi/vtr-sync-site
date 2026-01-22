<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitePost extends Model
{
    protected $guarded = false;

    protected $casts = [
        'meta' => 'array',
        'ai_meta' => 'array',
        'is_generated' => 'bool',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function categories()
    {
        return $this->belongsToMany(SiteCategory::class, 'site_post_category')
            ->where('site_categories.site_id', $this->site_id);
    }

    public function author()
    {
        return $this->belongsTo(SiteAuthor::class, 'author_id');
    }

    public function generationRun()
    {
        return $this->belongsTo(GenerationRun::class, 'generation_run_id');
    }

    public function generationTemplate()
    {
        return $this->belongsTo(GenerationTemplate::class, 'generation_template_id');
    }

    public function queryTopic()
    {
        return $this->belongsTo(QueryTopic::class, 'query_topic_id');
    }

}
