<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataImport;
use App\Models\Inventory;
use App\Services\InventoryResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected function currentImportId(): ?int
    {
        return DataImport::currentFull()->value('id');
    }

    /**
     * List inventory items for the current import (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0],
            ]);
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 25)));
        $query = Inventory::query()
            ->where('data_import_id', $importId)
            ->withCount('mpns');

        $vendor = trim((string) $request->input('vendor', ''));
        if ($vendor !== '') {
            $query->where('vendor_name', 'like', '%'.$vendor.'%');
        }

        $itemId = trim((string) $request->input('item_id', ''));
        if ($itemId !== '') {
            $query->where('item_id', 'like', '%'.$itemId.'%');
        }

        $status = (string) $request->input('status', '');
        if ($status === 'pending') {
            $query->whereNull('research_completed_at');
        } elseif ($status === 'done') {
            $query->whereNotNull('research_completed_at');
        }

        $query->orderBy('id');

        $paginator = $query->paginate($perPage);
        $items = $paginator->getCollection()->map(function (Inventory $inv) {
            return [
                'id' => $inv->id,
                'item_id' => $inv->item_id,
                'description' => $inv->description,
                'vendor_name' => $inv->vendor_name,
                'product_line' => $inv->product_line,
                'quantity' => $inv->quantity,
                'unit_cost' => $inv->unit_cost,
                'ext_cost' => $inv->ext_cost,
                'research_completed_at' => $inv->research_completed_at !== null ? $inv->research_completed_at->toIso8601String() : null,
                'mpns_count' => $inv->mpns_count ?? 0,
            ];
        });

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
     * Clear research for a single inventory (reset so it can be re-researched).
     */
    public function clearResearch(Inventory $inventory, InventoryResearchService $research): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null || $inventory->data_import_id !== $importId) {
            return response()->json(['message' => 'Inventory not found or not in current import.'], 404);
        }

        $research->clearInventoryResearch($inventory);

        return response()->json(['message' => 'Research cleared. Item will be picked up on the next research run.']);
    }

    /**
     * Clear research for all inventories in the current import.
     */
    public function clearAllResearch(InventoryResearchService $research): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return response()->json(['message' => 'No current import to clear.'], 404);
        }

        $import = DataImport::find($importId);
        if (! $import) {
            return response()->json(['message' => 'No current import to clear.'], 404);
        }

        $cleared = $research->clearAllForImport($import);
        if ($cleared === 0) {
            return response()->json(['message' => 'No inventory rows for current import.']);
        }

        return response()->json([
            'message' => 'Cleared research for all inventories in the current import.',
            'cleared_inventories' => $cleared,
        ]);
    }
}
