<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationRun extends Model
{
    protected $guarded = false;

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(GenerationTemplate::class, 'generation_template_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SitePost::class, 'generation_run_id');
    }
}

