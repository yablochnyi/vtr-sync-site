<?php

namespace App\Jobs;

use App\Models\GenerationRun;
use App\Services\Generation\GenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $runId)
    {
    }

    public function handle(GenerationService $service): void
    {
        $run = GenerationRun::query()->findOrFail($this->runId);
        $service->run($run);
    }
}

