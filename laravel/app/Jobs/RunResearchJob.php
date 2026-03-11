<?php

namespace App\Jobs;

use App\Models\DataImport;
use App\Models\Inventory;
use App\Models\ResearchRun;
use App\Services\AuditLogService;
use App\Services\GeminiResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $limit = 50,
        public ?int $researchRunId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(GeminiResearchService $gemini, AuditLogService $auditLog): void
    {
        $run = $this->researchRunId ? ResearchRun::find($this->researchRunId) : null;
        if ($run) {
            $run->update(['status' => 'running', 'message' => 'Research job started.']);
        }
        $auditLog->log('research.run.started', null, 'research_run', $run?->id, [
            'limit' => $this->limit,
        ]);

        try {
            $importId = DataImport::currentFull()->value('id');
            if ($importId === null) {
                if ($run) {
                    $run->update([
                        'status' => 'completed',
                        'message' => 'No current import. Nothing to process.',
                        'completed_at' => now(),
                    ]);
                }
                return;
            }

            $inventories = Inventory::query()
                ->where('data_import_id', $importId)
                ->whereNull('research_completed_at')
                ->with('mpns')
                ->orderBy('id')
                ->limit($this->limit)
                ->get();

            if ($inventories->isEmpty()) {
                if ($run) {
                    $run->update([
                        'status' => 'completed',
                        'message' => 'No inventory rows pending research.',
                        'gemini_hits' => 0,
                        'completed_at' => now(),
                    ]);
                }
                $auditLog->log('research.run.completed', null, 'research_run', $run?->id, ['gemini_hits' => 0]);
                return;
            }

            $geminiHits = 0;
            foreach ($inventories as $inventory) {
                $mpns = $inventory->mpns->pluck('part_number')->filter()->values()->all();
                $quantity = (float) ($inventory->quantity ?? 1);
                $vendorName = (string) ($inventory->vendor_name ?? '');
                $productLine = (string) ($inventory->product_line ?? '');

                $result = $gemini->lookup($vendorName, $productLine, $mpns, $quantity);
                $geminiHits++;

                if ($result['success'] ?? false) {
                    $gemini->persistLookupResult($inventory, $result);
                } else {
                    Log::warning('RunResearchJob: Gemini lookup failed for inventory '.$inventory->id, [
                        'error' => $result['error'] ?? 'Unknown',
                    ]);
                    // Leave research_completed_at null so it can be retried
                }
            }

            if ($run) {
                $run->update([
                    'status' => 'completed',
                    'message' => 'Processed '.$inventories->count().' inventory rows.',
                    'gemini_hits' => $geminiHits,
                    'completed_at' => now(),
                ]);
            }
            $auditLog->log('research.run.completed', null, 'research_run', $run?->id, [
                'gemini_hits' => $geminiHits,
                'processed' => $inventories->count(),
            ]);
        } catch (Throwable $e) {
            Log::error('RunResearchJob failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($run) {
                $run->update([
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
            }
            $auditLog->log('research.run.failed', null, 'research_run', $run?->id, [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        $run = $this->researchRunId ? ResearchRun::find($this->researchRunId) : null;
        if ($run) {
            $run->update([
                'status' => 'failed',
                'message' => $exception ? $exception->getMessage() : 'Job failed.',
                'completed_at' => now(),
            ]);
        }
    }
}
