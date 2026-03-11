<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Jobs\RunResearchJob;
use App\Models\DataImport;
use App\Models\FxSnapshot;
use App\Models\Item;
use App\Models\Mapping;
use App\Models\PriceFinding;
use App\Models\ResearchRun;
use App\Models\ResearchTask;
use App\Services\DigiKeyClient;
use App\Services\MouserClient;
use App\Services\NexarClient;
use App\Services\ClaudeResearchService;
use App\Services\AppSettingsService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementController extends Controller
{
    protected function currentImportId(): ?int
    {
        return DataImport::currentFull()->value('id');
    }

    protected function tasksForCurrentImport()
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return ResearchTask::query()->whereRaw('1 = 0');
        }
        return ResearchTask::query()->whereHas('item', fn ($q) => $q->where('data_import_id', $importId));
    }

    /**
     * 3.1.1 GET KPIs/summary
     */
    public function summary(): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return response()->json([
                'queue_status_counts' => [],
                'mapping_counts' => ['mapped' => 0, 'needs_mapping' => 0, 'non_catalog' => 0],
                'modeled_savings_total' => 0,
                'provider_hit_counts' => [],
            ]);
        }

        $taskQuery = $this->tasksForCurrentImport();
        $queueStatusCounts = (clone $taskQuery)->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $mappingRows = Mapping::query()
            ->where('data_import_id', $importId)
            ->get(['lookup_mode', 'mapping_status', 'mpn']);
        $mappingCounts = ['mapped' => 0, 'needs_mapping' => 0, 'non_catalog' => 0];
        foreach ($mappingRows as $m) {
            $mode = strtolower(trim($m->lookup_mode ?? ''));
            $status = strtolower(trim($m->mapping_status ?? ''));
            $mpn = trim($m->mpn ?? '');
            if (in_array($mode, ['non_catalog', 'noncatalog', 'custom'], true)) {
                $mappingCounts['non_catalog']++;
            } elseif ($mpn !== '' && in_array($status, ['mapped', 'verified'], true)) {
                $mappingCounts['mapped']++;
            } else {
                $mappingCounts['needs_mapping']++;
            }
        }

        $taskIds = (clone $taskQuery)->pluck('id');
        $modeledSavingsTotal = Action::query()
            ->whereIn('research_task_id', $taskIds)
            ->sum('estimated_savings');

        $providerHitCounts = PriceFinding::query()
            ->whereIn('research_task_id', $taskIds)
            ->selectRaw('provider, count(*) as count')
            ->groupBy('provider')
            ->pluck('count', 'provider')
            ->all();

        return response()->json([
            'queue_status_counts' => $queueStatusCounts,
            'mapping_counts' => $mappingCounts,
            'modeled_savings_total' => round((float) $modeledSavingsTotal, 4),
            'provider_hit_counts' => $providerHitCounts,
        ]);
    }

    /**
     * 6.1.4 GET analytics: supplier savings and daily trend.
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

        $taskIds = $this->tasksForCurrentImport()->pluck('id');

        $supplierSavings = Action::query()
            ->join('research_tasks', 'actions.research_task_id', '=', 'research_tasks.id')
            ->join('suppliers', 'research_tasks.supplier_id', '=', 'suppliers.id')
            ->whereIn('actions.research_task_id', $taskIds)
            ->selectRaw('suppliers.name as supplier_name, sum(actions.estimated_savings) as savings_total')
            ->groupBy('suppliers.name')
            ->orderByDesc('savings_total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'supplier_name' => $row->supplier_name,
                'savings_total' => round((float) $row->savings_total, 4),
            ])
            ->values()
            ->all();

        $dailySavings = Action::query()
            ->whereIn('research_task_id', $taskIds)
            ->selectRaw('DATE(created_at) as day, sum(estimated_savings) as savings_total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => (string) $row->day,
                'savings_total' => round((float) $row->savings_total, 4),
            ])
            ->values()
            ->all();

        return response()->json([
            'top_suppliers_by_savings' => $supplierSavings,
            'daily_modeled_savings' => $dailySavings,
        ]);
    }

    /**
     * 3.1.2 GET research queue: paginated, filters status, vendor, item search, priority
     */
    public function queue(Request $request): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return response()->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0]]);
        }

        $q = $this->tasksForCurrentImport()->with(['item:id,internal_part_number,description,data_import_id', 'supplier:id,name']);

        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('vendor')) {
            $q->whereHas('supplier', fn ($b) => $b->where('name', 'like', '%' . $request->input('vendor') . '%'));
        }
        if ($request->filled('item_search')) {
            $term = $request->input('item_search');
            $q->whereHas('item', fn ($b) => $b->where('internal_part_number', 'like', '%' . $term . '%')
                ->orWhere('description', 'like', '%' . $term . '%'));
        }
        if ($request->filled('priority')) {
            $q->where('priority', '<=', (int) $request->input('priority'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginator = $q->orderBy('priority')->paginate($perPage);

        $items = $paginator->getCollection()->map(fn ($t) => [
            'id' => $t->id,
            'task_type' => $t->task_type,
            'status' => $t->status,
            'priority' => $t->priority,
            'batch_id' => $t->batch_id,
            'notes' => $t->notes,
            'description' => $t->description,
            'spend_12m' => $t->spend_12m,
            'qty_12m' => $t->qty_12m,
            'avg_unit_cost_12m' => $t->avg_unit_cost_12m,
            'item' => $t->item ? ['id' => $t->item->id, 'internal_part_number' => $t->item->internal_part_number, 'description' => $t->item->description] : null,
            'supplier' => $t->supplier ? ['id' => $t->supplier->id, 'name' => $t->supplier->name] : null,
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * 3.1.3 GET price comparison: actions with best finding per task
     */
    public function priceComparison(Request $request): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return response()->json(['data' => []]);
        }

        $taskIds = $this->tasksForCurrentImport()->pluck('id');
        $actions = Action::query()
            ->whereIn('research_task_id', $taskIds)
            ->with(['researchTask.item', 'researchTask.supplier'])
            ->get();

        $taskIdsWithActions = $actions->pluck('research_task_id')->all();
        $bestFindings = PriceFinding::query()
            ->whereIn('research_task_id', $taskIdsWithActions)
            ->where('accepted', true)
            ->get()
            ->groupBy('research_task_id')
            ->map(fn ($list) => $list->sortBy('min_unit_price')->first());

        $data = $actions->map(function ($a) use ($bestFindings) {
            $task = $a->researchTask;
            $best = $bestFindings->get($a->research_task_id);
            return [
                'research_task_id' => $a->research_task_id,
                'estimated_savings' => $a->estimated_savings,
                'action_type' => $a->action_type,
                'priority_score' => $a->priority_score,
                'approval_status' => $a->approval_status,
                'task_type' => $task?->task_type,
                'item' => $task?->item ? ['id' => $task->item->id, 'internal_part_number' => $task->item->internal_part_number] : null,
                'supplier' => $task?->supplier ? ['id' => $task->supplier->id, 'name' => $task->supplier->name] : null,
                'avg_unit_cost_12m' => $task?->avg_unit_cost_12m,
                'qty_12m' => $task?->qty_12m,
                'best_finding' => $best ? [
                    'provider' => $best->provider,
                    'min_unit_price' => $best->min_unit_price,
                    'currency' => $best->currency,
                    'matched_mpn' => $best->matched_mpn,
                ] : null,
            ];
        });

        return response()->json(['data' => $data->values()->all()]);
    }

    /**
     * 3.1.4 GET research evidence: by task_id or item_id
     */
    public function evidence(Request $request): JsonResponse
    {
        $taskId = $request->input('task_id');
        $itemId = $request->input('item_id');

        if ($taskId !== null) {
            $task = ResearchTask::with(['item', 'supplier', 'priceFindings'])->find($taskId);
            if (! $task) {
                return response()->json(['task' => null, 'price_findings' => []], 404);
            }
            return response()->json([
                'task' => $this->formatTask($task),
                'price_findings' => $task->priceFindings->map(fn ($f) => [
                    'id' => $f->id,
                    'provider' => $f->provider,
                    'matched_mpn' => $f->matched_mpn,
                    'currency' => $f->currency,
                    'min_unit_price' => $f->min_unit_price,
                    'match_score' => $f->match_score,
                    'accepted' => $f->accepted,
                ])->all(),
            ]);
        }

        if ($itemId !== null) {
            $tasks = ResearchTask::with(['item', 'supplier', 'priceFindings'])
                ->where('item_id', $itemId)
                ->get();
            $out = $tasks->map(fn ($t) => [
                'task' => $this->formatTask($t),
                'price_findings' => $t->priceFindings->map(fn ($f) => [
                    'id' => $f->id,
                    'provider' => $f->provider,
                    'matched_mpn' => $f->matched_mpn,
                    'min_unit_price' => $f->min_unit_price,
                    'match_score' => $f->match_score,
                    'accepted' => $f->accepted,
                ])->all(),
            ])->all();
            return response()->json(['data' => $out]);
        }

        return response()->json(['error' => 'Provide task_id or item_id'], 400);
    }

    protected function formatTask(ResearchTask $t): array
    {
        return [
            'id' => $t->id,
            'task_type' => $t->task_type,
            'status' => $t->status,
            'priority' => $t->priority,
            'notes' => $t->notes,
            'item' => $t->item ? ['id' => $t->item->id, 'internal_part_number' => $t->item->internal_part_number, 'description' => $t->item->description] : null,
            'supplier' => $t->supplier ? ['id' => $t->supplier->id, 'name' => $t->supplier->name] : null,
        ];
    }

    /**
     * 3.1.5 GET vendor progress: per-vendor task counts, processed %, totals
     */
    public function vendorProgress(Request $request): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return response()->json(['data' => []]);
        }

        $base = $this->tasksForCurrentImport()->with('supplier:id,name');
        $byVendor = (clone $base)->selectRaw('supplier_id, count(*) as total')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $processed = (clone $base)->whereIn('status', ['researched', 'skipped_non_catalog', 'needs_mapping'])
            ->selectRaw('supplier_id, count(*) as count')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $supplierIds = $byVendor->keys()->all();
        $suppliers = \App\Models\Supplier::query()->whereIn('id', $supplierIds)->get()->keyBy('id');

        $data = $byVendor->map(function ($row) use ($processed, $suppliers) {
            $total = (int) $row->total;
            $done = (int) ($processed->get($row->supplier_id)?->count ?? 0);
            return [
                'supplier_id' => $row->supplier_id,
                'supplier_name' => $suppliers->get($row->supplier_id)?->name ?? '',
                'task_total' => $total,
                'task_processed' => $done,
                'processed_pct' => $total > 0 ? round($done / $total * 100, 1) : 0,
            ];
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * 3.1.6 GET mapping review queue; optional GET param for MPN worklist (top 20)
     */
    public function mappingReview(Request $request): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return response()->json(['data' => [], 'mpn_worklist' => []]);
        }

        $mappings = Mapping::query()
            ->where('data_import_id', $importId)
            ->with('item:id,internal_part_number,description,data_import_id')
            ->get();

        $needsReview = $mappings->filter(function ($m) {
            $status = strtolower(trim($m->mapping_status ?? ''));
            $mode = strtolower(trim($m->lookup_mode ?? ''));
            $mpn = trim($m->mpn ?? '');
            if (in_array($mode, ['non_catalog', 'noncatalog', 'custom'], true)) {
                return false;
            }
            if ($mpn !== '' && in_array($status, ['mapped', 'verified'], true)) {
                return false;
            }
            return true;
        })->map(fn ($m) => [
            'id' => $m->id,
            'item_id' => $m->item_id,
            'internal_part_number' => $m->item?->internal_part_number,
            'description' => $m->item?->description,
            'mpn' => $m->mpn,
            'mapping_status' => $m->mapping_status,
            'lookup_mode' => $m->lookup_mode,
        ])->values()->all();

        $worklistLimit = (int) $request->input('worklist_limit', 20);
        $mpnWorklist = array_slice($needsReview, 0, $worklistLimit);

        return response()->json([
            'data' => $needsReview,
            'mpn_worklist' => $mpnWorklist,
        ]);
    }

    /**
     * 3.1.7 GET system health: last research run, FX snapshot, providers enabled
     */
    public function systemHealth(): JsonResponse
    {
        $lastRun = ResearchTask::query()
            ->whereIn('status', ['researched', 'needs_research'])
            ->orderByDesc('updated_at')
            ->value('updated_at');

        $fx = FxSnapshot::query()->where('key', 'fx_rates')->first();
        $fxSnapshot = $fx ? ['captured_at' => $fx->created_at ?? null, 'base' => $fx->value_json['base'] ?? 'USD', 'rates_count' => count($fx->value_json['rates'] ?? [])] : null;

        $digiKey = app(DigiKeyClient::class);
        $mouser = app(MouserClient::class);
        $nexar = app(NexarClient::class);
        $claude = app(ClaudeResearchService::class);

        return response()->json([
            'last_research_run_at' => $lastRun?->toIso8601String(),
            'fx_snapshot' => $fxSnapshot,
            'providers_enabled' => [
                'digikey' => $digiKey->isEnabled(),
                'mouser' => $mouser->isEnabled(),
                'nexar' => $nexar->isEnabled(),
                'claude' => $claude->isEnabled(),
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

        $batchSize = (int) $request->input('batch_size', 50);
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
