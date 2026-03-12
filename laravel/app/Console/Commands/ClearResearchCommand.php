<?php

namespace App\Console\Commands;

use App\Models\AltVendor;
use App\Models\Inventory;
use App\Models\Mpn;
use App\Models\ResearchRun;
use Illuminate\Console\Command;

class ClearResearchCommand extends Command
{
    protected $signature = 'procurement:clear-research
                            {--runs-only : Only delete research_runs records; do not reset inventory/mpn/alt_vendors}
                            {--inventory-id= : Clear research for a single inventory by ID (no runs deleted)}';

    protected $description = 'Remove previous research runs and reset research-derived data so new research can run with the current schema. Does not clear researched_mpn (API/Gemini cache is preserved).';

    public function handle(): int
    {
        $inventoryId = $this->option('inventory-id');
        if ($inventoryId !== null && $inventoryId !== '') {
            return $this->clearInventoryResearch((int) $inventoryId);
        }

        $runsOnly = $this->option('runs-only');

        $deletedRuns = ResearchRun::query()->count();
        ResearchRun::query()->delete();
        $this->info("Deleted {$deletedRuns} research run(s).");

        if ($runsOnly) {
            $this->info('Skipping inventory/mpn/alt_vendors reset (--runs-only).');
            return self::SUCCESS;
        }

        AltVendor::query()->delete();
        $this->info('Deleted all alt_vendors.');

        Mpn::query()->update([
            'unit_price' => null,
            'url' => null,
            'price_fetched_at' => null,
            'currency' => null,
        ]);
        $this->info('Cleared unit_price, url, price_fetched_at, currency on all mpn rows.');

        Inventory::query()->update([
            'research_completed_at' => null,
            'gemini_response_json' => null,
        ]);
        $this->info('Cleared research_completed_at and gemini_response_json on all inventories.');

        $this->info('researched_mpn table left intact (cache preserved).');
        $this->info('Done. You can run research again with the new schema.');

        return self::SUCCESS;
    }

    protected function clearInventoryResearch(int $inventoryId): int
    {
        $inventory = Inventory::find($inventoryId);
        if (! $inventory) {
            $this->error("Inventory with ID {$inventoryId} not found.");

            return self::FAILURE;
        }

        $mpnIds = $inventory->mpns()->pluck('id')->all();
        if ($mpnIds !== []) {
            AltVendor::query()->whereIn('mpn_id', $mpnIds)->delete();
            $this->info('Deleted alt_vendors for this inventory\'s MPNs.');
            Mpn::query()->whereIn('id', $mpnIds)->update([
                'unit_price' => null,
                'url' => null,
                'price_fetched_at' => null,
                'currency' => null,
            ]);
            $this->info('Cleared unit_price, url, price_fetched_at, currency on this inventory\'s MPNs.');
        }

        $inventory->update([
            'research_completed_at' => null,
            'gemini_response_json' => null,
        ]);
        $this->info("Cleared research for inventory ID {$inventoryId}. It will be picked up on the next research run.");

        return self::SUCCESS;
    }
}
