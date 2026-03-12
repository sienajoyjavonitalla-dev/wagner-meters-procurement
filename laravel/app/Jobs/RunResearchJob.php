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
                ->whereHas('mpns', fn ($q) => $q->whereNotNull('part_number')->where('part_number', '!=', ''))
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

                $result = $vendorApi->tryLookup($inventory);
                if ($result === null) {
                    $vendorIsApiOnly = self::vendorIsDigiKeyOrMouser($vendorName);
                    if ($vendorIsApiOnly) {
                        Log::warning('RunResearchJob: Vendor is DigiKey/Mouser but API returned no results; not using Gemini for current vendor (mpn table is updated from API only). Skipping inventory.', [
                            'inventory_id' => $inventory->id,
                            'vendor_name' => $vendorName,
                        ]);
                        continue;
                    }
                    $result = $gemini->lookup($vendorName, $productLine, $mpns, $quantity);
                    $geminiHits++;
                } else {
                    // Current vendor = DigiKey or Mouser (API). Alt vendors: other API (e.g. Mouser if current=DigiKey) + Gemini (always).
                    Log::info('RunResearchJob: Vendor API lookup used for inventory '.$inventory->id, [
                        'source' => $result['source'] ?? 'api',
                    ]);
                    $geminiAlt = $gemini->lookupAltVendorsOnly($vendorName, $productLine, $mpns, $quantity);
                    if ($geminiAlt['success'] && ! empty($geminiAlt['alt_vendor_results'])) {
                        $result['alt_vendor_results'] = VendorApiResearchService::mergeAltVendorResults(
                            $result['alt_vendor_results'] ?? [],
                            $geminiAlt['alt_vendor_results']
                        );
                    } elseif (! empty($geminiAlt['error'])) {
                        Log::warning('RunResearchJob: Gemini alt-vendors-only failed for inventory '.$inventory->id, [
                            'error' => $geminiAlt['error'],
                        ]);
                    }
                }

                if ($result['success'] ?? false) {
                    if (! empty($result['prompt'])) {
                        $prompt = $result['prompt'];
                        Log::info('RunResearchJob: Gemini lookup succeeded for inventory '.$inventory->id, [
                            'prompt' => strlen($prompt) > 4000
                                ? substr($prompt, 0, 4000) . "\n... (truncated, total " . strlen($prompt) . " chars)"
                                : $prompt,
                        ]);
                    }
                    // Alt vendors: always include Gemini. If current vendor is not DigiKey/Mouser, also add DigiKey API + Mouser API.
                    $fromVendorApi = isset($result['source']) && in_array($result['source'], ['digikey_api', 'mouser_api'], true);
                    if (! $fromVendorApi) {
                        $apiAlt = $vendorApi->fetchAltVendorsFromApis($mpns, $quantity, $vendorName);
                        if ($apiAlt !== []) {
                            $result['alt_vendor_results'] = VendorApiResearchService::mergeAltVendorResults(
                                $result['alt_vendor_results'] ?? [],
                                $apiAlt
                            );
                        }
                    }
                    PersistGeminiResultJob::dispatch($inventory->id, $result);
                } else {
                    $context = ['error' => $result['error'] ?? 'Unknown'];
                    if (! empty($result['raw_text'])) {
                        $raw = $result['raw_text'];
                        $context['raw_response'] = strlen($raw) > 4000
                            ? substr($raw, 0, 4000) . "\n... (truncated, total " . strlen($raw) . " chars)"
                            : $raw;
                    }
                    if (! empty($result['prompt'])) {
                        $prompt = $result['prompt'];
                        $context['prompt'] = strlen($prompt) > 4000
                            ? substr($prompt, 0, 4000) . "\n... (truncated, total " . strlen($prompt) . " chars)"
                            : $prompt;
                    }
                    Log::warning('RunResearchJob: Gemini lookup failed for inventory '.$inventory->id, $context);
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

    private static function vendorIsDigiKeyOrMouser(string $vendorName): bool
    {
        $n = strtolower(trim($vendorName));
        $nNoSpaces = str_replace(' ', '', $n);
        return str_contains($nNoSpaces, 'digikey') || str_contains($n, 'digi-key') || str_contains($n, 'digi key') || str_contains($n, 'mouser');
    }
}
