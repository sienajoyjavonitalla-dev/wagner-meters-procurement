<?php

namespace App\Services;

use App\Models\DataImport;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\ResearchTask;
use App\Models\Supplier;
use App\Models\VendorPriority;
use App\Models\ItemSpread;
use Carbon\Carbon;
use Illuminate\Support\Str;

class QueueBuilderService
{
    public function __construct(
        protected AppSettingsService $settings,
        protected ?int $topVendors = null,
        protected ?int $itemsPerVendor = null,
        protected ?int $topSpreadItems = null,
    ) {
    }

    /**
     * Build research queue from the latest successful full import.
     * Creates research_tasks with task_type (pricing_benchmark, alternate_part) and status pending.
     *
     * @return array{created: int, batch_id: string, import_id: int|null}
     */
    public function build(?DataImport $import = null): array
    {
        $defaults = $this->settings->getResearchSettings();
        $topVendors = $this->topVendors ?? (int) ($defaults['top_vendors'] ?? 20);
        $itemsPerVendor = $this->itemsPerVendor ?? (int) ($defaults['items_per_vendor'] ?? 50);
        $topSpreadItems = $this->topSpreadItems ?? (int) ($defaults['top_spread_items'] ?? 100);

        $import = $import ?? DataImport::currentFull()->first();
        if (! $import) {
            return ['created' => 0, 'batch_id' => '', 'import_id' => null];
        }

        $batchId = Str::uuid()->toString();
        $cutoff = Carbon::now()->subDays(365);

        $wantedVendorNames = VendorPriority::query()
            ->where('data_import_id', $import->id)
            ->orderBy('priority_rank')
            ->limit($topVendors)
            ->pluck('vendor_name')
            ->all();

        $supplierIds = Supplier::query()
            ->where('data_import_id', $import->id)
            ->whereIn('name', $wantedVendorNames)
            ->pluck('id')
            ->all();

        if (empty($supplierIds)) {
            return ['created' => 0, 'batch_id' => $batchId, 'import_id' => $import->id];
        }

        $recentPurchases = Purchase::query()
            ->where('data_import_id', $import->id)
            ->where('order_date', '>=', $cutoff)
            ->whereIn('supplier_id', $supplierIds)
            ->where('unit_price', '>', 0)
            ->get();

        $aggregated = [];
        foreach ($recentPurchases as $p) {
            $key = $p->supplier_id . '_' . $p->item_id;
            $ext = $p->unit_price * $p->quantity;
            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'supplier_id' => $p->supplier_id,
                    'item_id' => $p->item_id,
                    'spend_12m' => 0,
                    'qty_12m' => 0,
                    'unit_sum' => 0,
                    'unit_count' => 0,
                ];
            }
            $aggregated[$key]['spend_12m'] += $ext;
            $aggregated[$key]['qty_12m'] += (float) $p->quantity;
            $aggregated[$key]['unit_sum'] += (float) $p->unit_price * (float) $p->quantity;
            $aggregated[$key]['unit_count'] += (float) $p->quantity;
        }

        $itemIds = array_unique(array_column($aggregated, 'item_id'));
        $items = Item::query()
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        foreach ($aggregated as $key => &$row) {
            $row['avg_unit_cost_12m'] = $row['unit_count'] > 0
                ? $row['unit_sum'] / $row['unit_count']
                : 0;
            $row['description'] = $items->get($row['item_id'])?->description ?? '';
        }
        unset($row);

        $bySupplier = [];
        foreach ($aggregated as $row) {
            $bySupplier[$row['supplier_id']][] = $row;
        }

        $baseRows = [];
        foreach ($bySupplier as $supplierId => $rows) {
            usort($rows, fn ($a, $b) => $b['spend_12m'] <=> $a['spend_12m']);
            $baseRows = array_merge($baseRows, array_slice($rows, 0, $itemsPerVendor));
        }

        $spreadPartNumbers = ItemSpread::query()
            ->where('data_import_id', $import->id)
            ->orderBy('id')
            ->limit($topSpreadItems)
            ->pluck('internal_part_number')
            ->all();

        $spreadItemIds = Item::query()
            ->where('data_import_id', $import->id)
            ->whereIn('internal_part_number', $spreadPartNumbers)
            ->pluck('id')
            ->all();

        $altRows = [];
        if (! empty($spreadItemIds)) {
            $lastPurchasePerItem = Purchase::query()
                ->where('data_import_id', $import->id)
                ->whereIn('item_id', $spreadItemIds)
                ->orderByDesc('order_date')
                ->get()
                ->unique('item_id')
                ->keyBy('item_id');

            $spreadItems = Item::query()->whereIn('id', $spreadItemIds)->get()->keyBy('id');
            foreach ($lastPurchasePerItem as $itemId => $purchase) {
                $item = $spreadItems->get($itemId);
                $altRows[] = [
                    'supplier_id' => $purchase->supplier_id,
                    'item_id' => $itemId,
                    'description' => $item?->description ?? '',
                    'spend_12m' => 0,
                    'qty_12m' => 0,
                    'avg_unit_cost_12m' => 0,
                    'task_type' => 'alternate_part',
                ];
            }
        }

        $allRows = array_merge(
            array_map(fn ($r) => array_merge($r, ['task_type' => 'pricing_benchmark']), $baseRows),
            $altRows
        );

        $seen = [];
        $unique = [];
        foreach ($allRows as $r) {
            $k = $r['supplier_id'] . '_' . $r['item_id'] . '_' . $r['task_type'];
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $unique[] = $r;
        }

        usort($unique, function ($a, $b) {
            if ($a['spend_12m'] !== $b['spend_12m']) {
                return $b['spend_12m'] <=> $a['spend_12m'];
            }
            if ($a['task_type'] !== $b['task_type']) {
                return strcmp($a['task_type'], $b['task_type']);
            }
            if ($a['supplier_id'] !== $b['supplier_id']) {
                return $a['supplier_id'] <=> $b['supplier_id'];
            }
            return $a['item_id'] <=> $b['item_id'];
        });

        $created = 0;
        $priority = 1;
        foreach ($unique as $row) {
            ResearchTask::create([
                'task_type' => $row['task_type'],
                'item_id' => $row['item_id'],
                'supplier_id' => $row['supplier_id'],
                'status' => 'pending',
                'priority' => $priority++,
                'batch_id' => $batchId,
                'notes' => null,
                'description' => $row['description'] ?? null,
                'spend_12m' => $row['spend_12m'] ?? null,
                'qty_12m' => $row['qty_12m'] ?? null,
                'avg_unit_cost_12m' => $row['avg_unit_cost_12m'] ?? null,
            ]);
            $created++;
        }

        return ['created' => $created, 'batch_id' => $batchId, 'import_id' => $import->id];
    }

    public function setTopVendors(int $n): self
    {
        $this->topVendors = $n;
        return $this;
    }

    public function setItemsPerVendor(int $n): self
    {
        $this->itemsPerVendor = $n;
        return $this;
    }

    public function setTopSpreadItems(int $n): self
    {
        $this->topSpreadItems = $n;
        return $this;
    }
}
