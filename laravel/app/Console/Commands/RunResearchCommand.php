<?php

namespace App\Console\Commands;

use App\Jobs\RunResearchJob;
use App\Models\DataImport;
use App\Services\AuditLogService;
use App\Services\GeminiResearchService;
use Illuminate\Console\Command;

class RunResearchCommand extends Command
{
    protected $signature = 'procurement:run-research
                            {--limit=5 : Max inventory rows to process (no research_completed_at)}
                            {--inventory-id= : Run research for a single inventory ID only (for testing; re-runs even if already researched)}
                            {--sync : Run research synchronously (default: dispatch job)}';

    protected $description = 'Run research: process up to N inventory rows via Gemini (batch of rows not yet researched). Use --inventory-id=ID to test a single item.';

    public function handle(): int
    {
        $import = DataImport::currentFull()->first();
        if (! $import) {
            $this->warn('No completed full import found. Run a data import first.');
            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit') ?: 5));
        $inventoryId = $this->option('inventory-id') !== null ? (int) $this->option('inventory-id') : null;
        $job = new RunResearchJob($limit, null, $inventoryId);

        if ($this->option('sync')) {
            $this->info('Running research synchronously...'.($inventoryId ? " (inventory ID: {$inventoryId})" : ''));
            $job->handle(
                app(\App\Services\GeminiResearchService::class),
                app(\App\Services\VendorApiResearchService::class),
                app(AuditLogService::class),
            );
            $this->info('Done.');
        } else {
            dispatch($job);
            $this->info('Research job dispatched. Process with: php artisan queue:work');
        }

        return self::SUCCESS;
    }
}
