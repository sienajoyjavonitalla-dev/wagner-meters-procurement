<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AltVendor;
use App\Models\DataImport;
use App\Models\Inventory;
use App\Models\Mpn;
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
            ->withCount('mpns')
            ->orderBy('id');

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
    public function clearResearch(Inventory $inventory): JsonResponse
    {
        $importId = $this->currentImportId();
        if ($importId === null || $inventory->data_import_id !== $importId) {
            return response()->json(['message' => 'Inventory not found or not in current import.'], 404);
        }

        $mpnIds = $inventory->mpns()->pluck('id')->all();
        if ($mpnIds !== []) {
            AltVendor::query()->whereIn('mpn_id', $mpnIds)->delete();
            Mpn::query()->whereIn('id', $mpnIds)->update([
                'unit_price' => null,
                'url' => null,
                'price_fetched_at' => null,
                'currency' => null,
            ]);
        }

        $inventory->update([
            'research_completed_at' => null,
            'gemini_response_json' => null,
        ]);

        return response()->json(['message' => 'Research cleared. Item will be picked up on the next research run.']);
    }
}
