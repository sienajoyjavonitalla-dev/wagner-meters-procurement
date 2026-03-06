<?php

namespace App\Console\Commands;

use App\Models\DataImport;
use App\Services\QueueBuilderService;
use Illuminate\Console\Command;

class BuildResearchQueueCommand extends Command
{
    protected $signature = 'procurement:build-queue
                            {--vendors=20 : Top N vendors by priority rank}
                            {--per-vendor=50 : Max items per vendor}
                            {--spread=100 : Top N items for alternate_part tasks}';

    protected $description = 'Build research task queue from the latest full data import';

    public function handle(QueueBuilderService $builder): int
    {
        $import = DataImport::currentFull()->first();
        if (! $import) {
            $this->warn('No completed full import found. Run a data import first.');
            return self::FAILURE;
        }

        $builder
            ->setTopVendors((int) $this->option('vendors'))
            ->setItemsPerVendor((int) $this->option('per-vendor'))
            ->setTopSpreadItems((int) $this->option('spread'));

        $result = $builder->build($import);

        $this->info("Queue built: {$result['created']} tasks (batch: {$result['batch_id']}).");
        return self::SUCCESS;
    }
}
