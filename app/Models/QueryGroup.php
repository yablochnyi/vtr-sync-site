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
                ->where(function ($q) {
                    $q->whereIn('status', ['pending', 'failed'])
                        ->orWhere(function ($q) {
                            $q->where('status', 'reserved')
                                ->whereNotNull('reserved_at')
                                ->where('reserved_at', '<', now()->subMinutes(30));
                        });
                })
                ->orderByRaw("case when status = 'pending' then 0 when status = 'failed' then 1 else 2 end")
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

