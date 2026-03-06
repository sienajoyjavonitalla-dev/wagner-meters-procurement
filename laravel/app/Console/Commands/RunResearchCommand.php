<?php

namespace App\Console\Commands;

use App\Jobs\RunResearchJob;
use App\Models\DataImport;
use App\Services\FxSnapshotService;
use App\Services\MappingService;
use App\Services\PostProcessResearchService;
use App\Services\QueueBuilderService;
use Illuminate\Console\Command;

class RunResearchCommand extends Command
{
    protected $signature = 'procurement:run-research
                            {--build : Build queue from latest import before running}
                            {--batch= : Process only tasks with this batch_id (UUID)}
                            {--limit=50 : Max pending tasks to process}
                            {--no-claude : Disable Claude AI fallback}
                            {--sync : Run research synchronously (default: dispatch job)}
                            {--fx : Capture FX snapshot after run}';

    protected $description = 'Run research pipeline: optionally build queue, process pending tasks, post-process actions, optional FX snapshot';

    public function handle(
        QueueBuilderService $queueBuilder,
        PostProcessResearchService $postProcess,
        FxSnapshotService $fxSnapshot,
    ): int {
        $import = DataImport::currentFull()->first();
        if (! $import) {
            $this->warn('No completed full import found. Run a data import first.');
            return self::FAILURE;
        }

        if ($this->option('build')) {
            $queueBuilder
                ->setTopVendors(20)
                ->setItemsPerVendor(50)
                ->setTopSpreadItems(100);
            $result = $queueBuilder->build($import);
            $this->info("Queue built: {$result['created']} tasks (batch: {$result['batch_id']}).");
        }

        $batchId = $this->option('batch') ?: null;
        $limit = (int) $this->option('limit');
        $useClaude = ! $this->option('no-claude');

        $job = new RunResearchJob($batchId, $limit, $useClaude);

        if ($this->option('sync')) {
            $this->info('Running research synchronously...');
            $job->handle(
                app(\App\Services\DigiKeyClient::class),
                app(\App\Services\MouserClient::class),
                app(\App\Services\NexarClient::class),
                app(\App\Services\ClaudeResearchService::class),
                app(MappingService::class),
            );
        } else {
            dispatch($job);
            $this->info('Research job dispatched. Process with: php artisan queue:work');
            return self::SUCCESS;
        }

        $actionsCount = $postProcess->process();
        $this->info("Post-process: {$actionsCount} actions upserted.");

        if ($this->option('fx')) {
            $rates = $fxSnapshot->capture();
            $this->info('FX snapshot captured: ' . count($rates) . ' rates.');
        }

        return self::SUCCESS;
    }
}
