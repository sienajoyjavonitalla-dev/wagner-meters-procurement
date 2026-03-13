<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunResearchJob;
use App\Models\AltVendor;
use App\Models\DataImport;
use App\Models\Inventory;
use App\Models\Mpn;
use App\Models\ResearchRun;
use App\Services\AppSettingsService;
use App\Services\AuditLogService;
use App\Services\GeminiResearchService;
use App\Services\ProcurementReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementController extends Controller
{
    protected function currentImportId(): ?int
    {
        return DataImport::currentFull()->value('id');
    }

    /**
     * 3.1.1 GET KPIs/summary (inventory-based)
     */
    public function summary(ProcurementReportingService $reporting): JsonResponse
    {
        return response()->json($reporting->buildSummary());
    }

    /**
     * 6.1.4 GET analytics: savings potential by vendor, optional daily trend.
     */
    public function analytics(ProcurementReportingService $reporting): JsonResponse
    {
        return response()->json($reporting->buildAnalytics());
    }

    /**
     * 3.1.2 GET research queue (stub)
     */
    public function queue(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0],
        ]);
    }

    /**
     * 3.1.3 GET price comparison (inventory-based: current vs lowest current vendor, vs lowest alt vendor)
     */
    public function priceComparison(Request $request): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return response()->json(['data' => []]);
        }

        $inventories = Inventory::query()
            ->where('data_import_id', $importId)
            ->whereNotNull('research_completed_at')
            ->with(['mpns', 'altVendors.mpn'])
            ->orderBy('id')
            ->get();

        $data = [];
        foreach ($inventories as $inv) {
            $currentCost = (float) ($inv->unit_cost ?? 0);
            $mpnsWithPrice = $inv->mpns->whereNotNull('unit_price');
            $lowestMpnVal = $mpnsWithPrice->isEmpty() ? null : (float) $mpnsWithPrice->min('unit_price');
            $currentVendorName = (string) ($inv->vendor_name ?? '');
            $lowestMpnRow = $inv->mpns->whereNotNull('unit_price')->sortBy('unit_price')->first();
            $rawUrl = $lowestMpnRow ? $lowestMpnRow->url : null;
            $currentVendorUrl = ($rawUrl !== null && trim((string) $rawUrl) !== '') ? trim((string) $rawUrl) : null;

            $lowestAlt = $inv->altVendors->sortBy('unit_price')->first();
            $lowestAltPrice = $lowestAlt ? (float) $lowestAlt->unit_price : null;
            $lowestAltVendorName = $lowestAlt ? (string) $lowestAlt->vendor_name : null;
            $rawAltUrl = $lowestAlt ? ($lowestAlt->url ?? null) : null;
            $lowestAltUrl = ($rawAltUrl !== null && trim((string) $rawAltUrl) !== '') ? trim((string) $rawAltUrl) : null;

            $quantity = (float) ($inv->quantity ?? 0);
            $extCost = (float) ($inv->ext_cost ?? 0);
            $savingsVsCurrent = ($lowestMpnVal !== null && $quantity > 0)
                ? round($extCost - ($lowestMpnVal * $quantity), 4) : null;
            $savingsVsAlt = ($lowestAltPrice !== null && $quantity > 0)
                ? round($extCost - ($lowestAltPrice * $quantity), 4) : null;

            $mpnList = $inv->mpns->pluck('part_number')->filter()->values()->implode("\n");

            $allAltVendors = $inv->altVendors->sortBy('unit_price')->values()->map(function ($av) use ($extCost, $quantity) {
                $up = (float) ($av->unit_price ?? 0);
                $savings = ($quantity > 0) ? round($extCost - ($up * $quantity), 4) : null;
                $partNumber = $av->relationLoaded('mpn') && $av->mpn
                    ? (string) ($av->mpn->part_number ?? '')
                    : '';

                return [
                    'part_number' => $partNumber,
                    'vendor_name' => (string) ($av->vendor_name ?? ''),
                    'unit_price' => $up,
                    'url' => ($av->url && trim((string) $av->url) !== '') ? trim((string) $av->url) : null,
                    'savings' => $savings,
                ];
            })->all();

            $data[] = [
                'inventory_id' => $inv->id,
                'item_id' => $inv->item_id,
                'description' => $inv->description,
                'vendor_name' => $currentVendorName,
                'mpn_list' => $mpnList,
                'unit_cost' => $currentCost,
                'quantity' => $inv->quantity,
                'ext_cost' => $extCost,
                'lowest_current_vendor_price' => $lowestMpnVal,
                'current_vendor_name' => $currentVendorName,
                'current_vendor_url' => $currentVendorUrl,
                'savings_vs_current_vendor' => $savingsVsCurrent,
                'lowest_alt_vendor_price' => $lowestAltPrice,
                'lowest_alt_vendor_name' => $lowestAltVendorName,
                'lowest_alt_vendor_url' => $lowestAltUrl ?: null,
                'savings_vs_alt_vendor' => $savingsVsAlt,
                'alt_vendors' => $allAltVendors,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * 3.1.4 GET research evidence (stub)
     */
    public function evidence(Request $request): JsonResponse
    {
        return response()->json(['task' => null, 'price_findings' => []], 404);
    }

    /**
     * 3.1.5 GET vendor progress (stub)
     */
    public function vendorProgress(Request $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    /**
     * 3.1.6 GET mapping review (stub)
     */
    public function mappingReview(Request $request): JsonResponse
    {
        return response()->json(['data' => [], 'mpn_worklist' => []]);
    }

    /**
     * 3.1.7 GET system health: last research run, providers enabled (Gemini)
     */
    public function systemHealth(): JsonResponse
    {
        $lastRun = ResearchRun::query()
            ->whereIn('status', ['completed', 'failed'])
            ->orderByDesc('completed_at')
            ->first();

        $gemini = app(GeminiResearchService::class);

        return response()->json([
            'last_research_run_at' => $lastRun && $lastRun->completed_at ? $lastRun->completed_at->format('c') : null,
            'fx_snapshot' => null,
            'providers_enabled' => [
                'gemini' => $gemini->isEnabled(),
            ],
        ]);
    }

    /**
     * 3.2.1 POST trigger research run (inventory-based, Gemini)
     */
    public function triggerRun(Request $request, AuditLogService $auditLog): JsonResponse
    {
        $import = DataImport::currentFull()->first();
        if (! $import) {
            return response()->json(['error' => 'No completed full import found. Run a data import first.'], 422);
        }

        $batchSize = (int) $request->input('batch_size', 5);
        $batchSize = max(1, min($batchSize, 500));

        $run = ResearchRun::create([
            'status' => 'pending',
            'batch_id' => null,
            'limit' => $batchSize,
            'use_claude' => false,
            'use_gemini' => true,
            'build_queue' => false,
            'message' => 'Job queued.',
        ]);

        dispatch(new RunResearchJob($batchSize, $run->id));

        $auditLog->log('research.run.queued', $request->user() ? $request->user()->id : null, 'research_run', $run->id, [
            'batch_size' => $batchSize,
        ]);

        $statusUrl = url('/api/procurement/run-status?run_id='.$run->id);

        return response()->json([
            'run_id' => $run->id,
            'status_url' => $statusUrl,
        ], 202);
    }

    /**
     * 6.1.5 GET runtime settings.
     */
    public function settings(AppSettingsService $settings): JsonResponse
    {
        return response()->json([
            'research' => $settings->getResearchSettings(),
        ]);
    }

    /**
     * 6.1.5 POST runtime settings update (admin).
     */
    public function updateSettings(
        Request $request,
        AppSettingsService $settings,
        AuditLogService $auditLog
    ): JsonResponse {
        $validated = $request->validate([
            'strict_mapping' => ['sometimes', 'boolean'],
            'min_match_score' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'claude_batch_size' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'gemini_batch_size' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'top_vendors' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'items_per_vendor' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'top_spread_items' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'nightly_enabled' => ['sometimes', 'boolean'],
            'nightly_time' => ['sometimes', 'date_format:H:i'],
        ]);

        $payload = collect($validated)->only([
            'strict_mapping',
            'min_match_score',
            'claude_batch_size',
            'gemini_batch_size',
            'top_vendors',
            'items_per_vendor',
            'top_spread_items',
            'nightly_enabled',
            'nightly_time',
        ])->all();

        $updated = $settings->updateResearchSettings($payload);
        $auditLog->log('settings.updated', $request->user() ? $request->user()->id : null, 'system_setting', AppSettingsService::RESEARCH_KEY, [
            'updated' => $updated,
        ]);

        return response()->json([
            'ok' => true,
            'research' => $updated,
        ]);
    }

    /**
     * 3.2.2 GET run status/logs for polling
     */
    public function runStatus(Request $request): JsonResponse
    {
        $runId = $request->input('run_id');
        $latest = $request->boolean('latest');

        if ($latest) {
            $run = ResearchRun::query()->orderByDesc('id')->first();
        } elseif ($runId !== null && $runId !== '') {
            $run = ResearchRun::find((int) $runId);
        } else {
            return response()->json(['error' => 'Provide run_id or latest=1'], 400);
        }

        if (! $run) {
            return response()->json(['error' => 'Run not found.'], 404);
        }

        return response()->json([
            'run_id' => $run->id,
            'status' => $run->status,
            'message' => $run->message,
            'completed_at' => $run->completed_at ? $run->completed_at->toIso8601String() : null,
            'created_at' => $run->created_at->toIso8601String(),
        ]);
    }

}
