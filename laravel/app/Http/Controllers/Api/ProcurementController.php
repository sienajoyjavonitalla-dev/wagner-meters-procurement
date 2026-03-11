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
    public function summary(): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return response()->json([
                'queue_status_counts' => ['researched' => 0, 'pending' => 0],
                'provider_hit_counts' => [],
                'savings_potential_per_vendor' => [],
            ]);
        }

        $inventories = Inventory::query()
            ->where('data_import_id', $importId)
            ->with(['mpns', 'altVendors'])
            ->get();

        $researched = $inventories->whereNotNull('research_completed_at')->count();
        $pending = $inventories->whereNull('research_completed_at')->count();
        $queueStatusCounts = [
            'researched' => $researched,
            'pending' => $pending,
        ];

        $geminiTotal = (int) ResearchRun::query()->sum('gemini_hits');
        $providerHitCounts = $geminiTotal > 0 ? ['gemini' => $geminiTotal] : [];

        $savingsByVendor = [];
        foreach ($inventories as $inv) {
            $current = (float) ($inv->unit_cost ?? 0);
            $lowestMpn = $inv->mpns->whereNotNull('unit_price')->min('unit_price');
            $lowestAlt = $inv->altVendors->min('unit_price');
            $lowest = null;
            if ($lowestMpn !== null && $lowestAlt !== null) {
                $lowest = min((float) $lowestMpn, (float) $lowestAlt);
            } elseif ($lowestMpn !== null) {
                $lowest = (float) $lowestMpn;
            } elseif ($lowestAlt !== null) {
                $lowest = (float) $lowestAlt;
            }
            if ($lowest !== null && $current > $lowest) {
                $vendor = (string) ($inv->vendor_name ?? 'Unknown');
                $savingsByVendor[$vendor] = ($savingsByVendor[$vendor] ?? 0) + ($current - $lowest);
            }
        }
        arsort($savingsByVendor);
        $savingsPotentialPerVendor = [];
        foreach (array_slice($savingsByVendor, 0, 10, true) as $vendorName => $total) {
            $savingsPotentialPerVendor[] = [
                'vendor_name' => $vendorName,
                'savings_total' => round((float) $total, 4),
            ];
        }

        return response()->json([
            'queue_status_counts' => $queueStatusCounts,
            'provider_hit_counts' => $providerHitCounts,
            'savings_potential_per_vendor' => $savingsPotentialPerVendor,
        ]);
    }

    /**
     * 6.1.4 GET analytics: savings potential by vendor, optional daily trend.
     */
    public function analytics(): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return response()->json([
                'top_suppliers_by_savings' => [],
                'daily_modeled_savings' => [],
            ]);
        }

        $inventories = Inventory::query()
            ->where('data_import_id', $importId)
            ->whereNotNull('research_completed_at')
            ->with(['mpns', 'altVendors'])
            ->get();

        $savingsByVendor = [];
        foreach ($inventories as $inv) {
            $current = (float) ($inv->unit_cost ?? 0);
            $lowestMpn = $inv->mpns->whereNotNull('unit_price')->min('unit_price');
            $lowestAlt = $inv->altVendors->min('unit_price');
            $lowest = null;
            if ($lowestMpn !== null && $lowestAlt !== null) {
                $lowest = min((float) $lowestMpn, (float) $lowestAlt);
            } elseif ($lowestMpn !== null) {
                $lowest = (float) $lowestMpn;
            } elseif ($lowestAlt !== null) {
                $lowest = (float) $lowestAlt;
            }
            if ($lowest !== null && $current > $lowest) {
                $vendor = (string) ($inv->vendor_name ?? 'Unknown');
                $savingsByVendor[$vendor] = ($savingsByVendor[$vendor] ?? 0) + ($current - $lowest);
            }
        }
        arsort($savingsByVendor);
        $topSuppliers = [];
        foreach (array_slice($savingsByVendor, 0, 10, true) as $vendorName => $total) {
            $topSuppliers[] = [
                'supplier_name' => $vendorName,
                'savings_total' => round((float) $total, 4),
            ];
        }

        $byDay = [];
        foreach ($inventories as $inv) {
            $day = $inv->research_completed_at?->format('Y-m-d');
            if ($day === null) {
                continue;
            }
            $current = (float) ($inv->unit_cost ?? 0);
            $lowestMpn = $inv->mpns->whereNotNull('unit_price')->min('unit_price');
            $lowestAlt = $inv->altVendors->min('unit_price');
            $lowest = null;
            if ($lowestMpn !== null && $lowestAlt !== null) {
                $lowest = min((float) $lowestMpn, (float) $lowestAlt);
            } elseif ($lowestMpn !== null) {
                $lowest = (float) $lowestMpn;
            } elseif ($lowestAlt !== null) {
                $lowest = (float) $lowestAlt;
            }
            $savings = ($lowest !== null && $current > $lowest) ? ($current - $lowest) : 0;
            $byDay[$day] = ($byDay[$day] ?? 0) + $savings;
        }
        ksort($byDay);
        $dailySavings = [];
        foreach ($byDay as $day => $total) {
            $dailySavings[] = ['day' => $day, 'savings_total' => round((float) $total, 4)];
        }

        return response()->json([
            'top_suppliers_by_savings' => $topSuppliers,
            'daily_modeled_savings' => $dailySavings,
        ]);
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
            ->with(['mpns', 'altVendors'])
            ->orderBy('id')
            ->get();

        $data = [];
        foreach ($inventories as $inv) {
            $currentCost = (float) ($inv->unit_cost ?? 0);
            $lowestMpn = $inv->mpns->whereNotNull('unit_price')->min('unit_price');
            $lowestMpnVal = $lowestMpn !== null ? (float) $lowestMpn : null;
            $currentVendorName = (string) ($inv->vendor_name ?? '');
            $gemini = $inv->gemini_response_json;
            $currentVendorUrl = is_array($gemini) ? ($gemini['current_vendor_url'] ?? null) : null;

            $lowestAlt = $inv->altVendors->sortBy('unit_price')->first();
            $lowestAltPrice = $lowestAlt ? (float) $lowestAlt->unit_price : null;
            $lowestAltVendorName = $lowestAlt ? (string) $lowestAlt->vendor_name : null;
            $lowestAltUrl = $lowestAlt ? (string) ($lowestAlt->url ?? '') : null;

            $savingsVsCurrent = ($lowestMpnVal !== null && $currentCost > $lowestMpnVal)
                ? round($currentCost - $lowestMpnVal, 4) : null;
            $savingsVsAlt = ($lowestAltPrice !== null && $currentCost > $lowestAltPrice)
                ? round($currentCost - $lowestAltPrice, 4) : null;

            $data[] = [
                'inventory_id' => $inv->id,
                'item_id' => $inv->item_id,
                'description' => $inv->description,
                'vendor_name' => $currentVendorName,
                'unit_cost' => $currentCost,
                'quantity' => $inv->quantity,
                'lowest_current_vendor_price' => $lowestMpnVal,
                'current_vendor_name' => $currentVendorName,
                'current_vendor_url' => $currentVendorUrl,
                'savings_vs_current_vendor' => $savingsVsCurrent,
                'lowest_alt_vendor_price' => $lowestAltPrice,
                'lowest_alt_vendor_name' => $lowestAltVendorName,
                'lowest_alt_vendor_url' => $lowestAltUrl ?: null,
                'savings_vs_alt_vendor' => $savingsVsAlt,
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
            'last_research_run_at' => $lastRun?->completed_at?->format('c'),
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

        $auditLog->log('research.run.queued', $request->user()?->id, 'research_run', $run->id, [
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
        $auditLog->log('settings.updated', $request->user()?->id, 'system_setting', AppSettingsService::RESEARCH_KEY, [
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
            'completed_at' => $run->completed_at?->toIso8601String(),
            'created_at' => $run->created_at->toIso8601String(),
        ]);
    }

}
