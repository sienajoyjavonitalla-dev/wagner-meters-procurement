<?php

namespace App\Jobs;

use App\Models\DataImport;
use App\Models\Inventory;
use App\Models\ResearchRun;
use App\Services\AuditLogService;
use App\Services\GeminiResearchService;
use App\Services\VendorApiResearchService;
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
        public int $limit = 5,
        public ?int $researchRunId = null,
        public ?int $inventoryId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(GeminiResearchService $gemini, VendorApiResearchService $vendorApi, AuditLogService $auditLog): void
    {
        $run = $this->researchRunId ? ResearchRun::find($this->researchRunId) : null;
        if ($run) {
            $run->update(['status' => 'running', 'message' => 'Research job started.']);
        }
        $auditLog->log('research.run.started', null, 'research_run', $run?->id, [
            'limit' => $this->limit,
            'inventory_id' => $this->inventoryId,
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

            $query = Inventory::query()
                ->where('data_import_id', $importId)
                ->whereHas('mpns', fn ($q) => $q->whereNotNull('part_number')->where('part_number', '!=', ''))
                ->with('mpns');

            if ($this->inventoryId !== null) {
                $query->where('id', $this->inventoryId);
                // For single-item test run, allow already-researched items (no filter on research_completed_at).
            } else {
                $query->whereNull('research_completed_at')->orderBy('id')->limit($this->limit);
            }

            $inventories = $query->get();

            if ($inventories->isEmpty()) {
                $message = $this->inventoryId !== null
                    ? 'Inventory ID '.$this->inventoryId.' not found, or not in current import, or has no MPNs.'
                    : 'No inventory rows pending research.';
                if ($run) {
                    $run->update([
                        'status' => 'completed',
                        'message' => $message,
                        'gemini_hits' => 0,
                        'completed_at' => now(),
                    ]);
                }
                $auditLog->log('research.run.completed', null, 'research_run', $run?->id, ['gemini_hits' => 0]);
                return;
            }

            $geminiHits = 0;
            $successCount = 0;
            foreach ($inventories as $inventory) {
                $mpns = $inventory->mpns->pluck('part_number')->filter()->values()->all();
                $quantity = (float) ($inventory->quantity ?? 1);
                $vendorName = (string) ($inventory->vendor_name ?? '');
                $productLine = (string) ($inventory->product_line ?? '');

                $result = $vendorApi->tryLookup($inventory);
                if ($result === null) {
                    $vendorIsApiOnly = self::vendorIsDigiKeyOrMouser($vendorName);
                    if ($vendorIsApiOnly) {
                        continue;
                    }
                    $result = $gemini->lookup($vendorName, $productLine, $mpns, $quantity, $inventory->id);
                    $geminiHits++;
                } else {
                    $geminiAlt = $gemini->lookupAltVendorsOnly($vendorName, $productLine, $mpns, $quantity, $inventory->id);
                    if ($geminiAlt['success'] && ! empty($geminiAlt['alt_vendor_results'])) {
                        $result['alt_vendor_results'] = VendorApiResearchService::mergeAltVendorResults(
                            $result['alt_vendor_results'] ?? [],
                            $geminiAlt['alt_vendor_results']
                        );
                    }
                }

                if ($result['success'] ?? false) {
                    // Alt vendors: always include Gemini. If current vendor is not DigiKey/Mouser, also add DigiKey API + Mouser API.
                    $fromVendorApi = isset($result['source']) && in_array($result['source'], ['digikey_api', 'mouser_api'], true);
                    if (! $fromVendorApi) {
                        $apiAlt = $vendorApi->fetchAltVendorsFromApis($mpns, $quantity, $vendorName, $inventory->id);
                        if ($apiAlt !== []) {
                            $result['alt_vendor_results'] = VendorApiResearchService::mergeAltVendorResults(
                                $result['alt_vendor_results'] ?? [],
                                $apiAlt
                            );
                        }
                    }
                    PersistGeminiResultJob::dispatch($inventory->id, $result);
                    $successCount++;
                } else {
                    // Leave research_completed_at null so it can be retried
                }
            }

            $failedCount = $inventories->count() - $successCount;
            $message = 'Processed '.$inventories->count().' inventory rows.';
            if ($failedCount > 0) {
                $message .= ' '.$failedCount.' failed (will retry automatically in ~5 min).';
                // Schedule a retry so unresearched items (failed ones) get picked up again.
                $retryRun = ResearchRun::create([
                    'status' => 'pending',
                    'batch_id' => null,
                    'limit' => $this->limit,
                    'use_claude' => false,
                    'use_gemini' => true,
                    'build_queue' => false,
                    'message' => 'Scheduled retry in 5 min for failed items.',
                ]);
                self::dispatch($this->limit, $retryRun->id)->delay(now()->addMinutes(5));
            }

            if ($run) {
                $run->update([
                    'status' => 'completed',
                    'message' => $message,
                    'gemini_hits' => $geminiHits,
                    'completed_at' => now(),
                ]);
            }
            $auditLog->log('research.run.completed', null, 'research_run', $run?->id, [
                'gemini_hits' => $geminiHits,
                'processed' => $inventories->count(),
                'success_count' => $successCount,
                'failed_count' => $failedCount,
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

    private static function vendorIsDigiKeyOrMouser(string $vendorName): bool
    {
        $n = strtolower(trim($vendorName));
        $nNoSpaces = str_replace(' ', '', $n);
        return str_contains($nNoSpaces, 'digikey') || str_contains($n, 'digi-key') || str_contains($n, 'digi key') || str_contains($n, 'mouser');
    }
}
