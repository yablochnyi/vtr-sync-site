<?php

namespace App\Console\Commands;

use App\Jobs\RunGenerationJob;
use App\Models\GenerationRun;
use App\Models\GenerationTemplate;
use Illuminate\Console\Command;

class RunGeneration extends Command
{
    protected $signature = 'generate:run {template_id} {--sync : Run immediately without queue}';

    protected $description = 'Start generation run from a template';

    public function handle(): int
    {
        $template = GenerationTemplate::query()->findOrFail((int) $this->argument('template_id'));

        $run = GenerationRun::create([
            'generation_template_id' => $template->id,
            'name' => $template->name . ' — ' . now()->format('Y-m-d H:i:s'),
            'status' => 'queued',
            'requested' => $template->articles_per_run,
            'generated' => 0,
            'meta' => [
                'template_snapshot' => $template->toArray(),
            ],
        ]);

        if ((bool) $this->option('sync')) {
            $this->info("Running synchronously: run_id={$run->id}");
            RunGenerationJob::dispatchSync($run->id);
        } else {
            $this->info("Queued: run_id={$run->id}");
            RunGenerationJob::dispatch($run->id);
        }

        return self::SUCCESS;
    }
}

