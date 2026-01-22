<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class QueryGroup extends Model
{
    protected $guarded = false;

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(QueryTopic::class);
    }


    public function reserveNextTopic(): ?QueryTopic
    {
        return DB::transaction(function () {
            /** @var QueryTopic|null $topic */
            $topic = $this->topics()
                ->where('status', 'pending')
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$topic) {
                return null;
            }

            $topic->update([
                'status' => 'reserved',
                'reserved_at' => now(),
            ]);

            return $topic->fresh();
        });
    }
}

