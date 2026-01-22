<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationTemplate extends Model
{
    protected $guarded = false;

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function queryGroup(): BelongsTo
    {
        return $this->belongsTo(QueryGroup::class, 'query_group_id');
    }

    public function articlePromt(): BelongsTo
    {
        return $this->belongsTo(Promt::class, 'article_promt_id');
    }

    public function slugPromt(): BelongsTo
    {
        return $this->belongsTo(Promt::class, 'slug_promt_id');
    }

    public function categoryPromt(): BelongsTo
    {
        return $this->belongsTo(Promt::class, 'category_promt_id');
    }

    public function rewritePromt(): BelongsTo
    {
        return $this->belongsTo(Promt::class, 'rewrite_promt_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(SiteAuthor::class, 'author_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(GenerationRun::class);
    }
}

