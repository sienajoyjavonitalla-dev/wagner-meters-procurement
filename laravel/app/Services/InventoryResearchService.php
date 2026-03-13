<?php

namespace App\Services;

use App\Models\AltVendor;
use App\Models\DataImport;
use App\Models\Inventory;
use App\Models\Mpn;

class InventoryResearchService
{
    public function clearInventoryResearch(Inventory $inventory): void
    {
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
    }

    /**
     * Clear research-related data for all inventories in a given full import.
     *
     * @return int number of inventories cleared
     */
    public function clearAllForImport(DataImport $import): int
    {
        $inventoryIds = Inventory::query()
            ->where('data_import_id', $import->id)
            ->pluck('id')
            ->all();

        if ($inventoryIds === []) {
            return 0;
        }

        $mpnIds = Mpn::query()
            ->whereIn('inventory_id', $inventoryIds)
            ->pluck('id')
            ->all();

        if ($mpnIds !== []) {
            AltVendor::query()->whereIn('mpn_id', $mpnIds)->delete();
            Mpn::query()->whereIn('id', $mpnIds)->update([
                'unit_price' => null,
                'url' => null,
                'price_fetched_at' => null,
                'currency' => null,
            ]);
        }

        Inventory::query()
            ->whereIn('id', $inventoryIds)
            ->update([
                'research_completed_at' => null,
                'gemini_response_json' => null,
            ]);

        return count($inventoryIds);
    }
}

