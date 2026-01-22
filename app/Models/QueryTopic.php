<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryTopic extends Model
{
    protected $guarded = false;

    protected $casts = [
        'reserved_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(QueryGroup::class, 'query_group_id');
    }

    public function markUsed(): void
    {
        $this->update([
            'status' => 'used',
            'used_at' => now(),
            'reserved_at' => null,
        ]);
    }
}

