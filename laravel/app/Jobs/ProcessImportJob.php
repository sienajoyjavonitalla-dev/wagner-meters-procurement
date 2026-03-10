<?php

namespace App\Jobs;

use App\Models\DataImport;
use App\Models\Item;
use App\Models\ItemSpread;
use App\Models\Mapping;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\VendorPriority;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public DataImport $dataImport,
        public string $inventoryPath,
        public string $vendorPriorityPath,
        public string $itemSpreadPath,
        public ?string $mpnMapPath = null
    ) {}

    public function handle(AuditLogService $auditLog): void
    {
        $import = $this->dataImport;
        $auditLog->log('import.process.started', null, 'data_import', $import->id);

        try {
            $previousId = DataImport::where('type', 'full')
                ->where('status', 'completed')
                ->where('id', '!=', $import->id)
                ->orderByDesc('id')
                ->value('id');

            if ($previousId) {
                Supplier::where('data_import_id', $previousId)->delete();
                Item::where('data_import_id', $previousId)->delete();
                Purchase::where('data_import_id', $previousId)->delete();
                Mapping::where('data_import_id', $previousId)->delete();
                VendorPriority::where('data_import_id', $previousId)->delete();
                ItemSpread::where('data_import_id', $previousId)->delete();
            }

            $disk = Storage::disk('imports');
            $rowCounts = $this->parseInventory($this->normalizePath($disk->path($this->inventoryPath)), $import->id);
            $rowCounts['vendor_priorities'] = $this->parseVendorPriority($this->normalizePath($disk->path($this->vendorPriorityPath)), $import->id);
            $rowCounts['item_spreads'] = $this->parseItemSpread($this->normalizePath($disk->path($this->itemSpreadPath)), $import->id);
            $rowCounts['mappings'] = $this->mpnMapPath
                ? $this->parseMpnMap($this->normalizePath($disk->path($this->mpnMapPath)), $import->id)
                : 0;

            $import->update([
                'status' => 'completed',
                'row_counts' => $rowCounts,
            ]);
            $auditLog->log('import.process.completed', null, 'data_import', $import->id, [
                'row_counts' => $rowCounts,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessImportJob failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $import->update([
                'status' => 'failed',
                'row_counts' => $import->row_counts ?? [],
            ]);
            $auditLog->log('import.process.failed', null, 'data_import', $import->id, [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], [DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], $path);
    }

    /** @return array{suppliers: int, items: int, purchases: int} */
    private function parseInventory(string $path, int $dataImportId): array
    {
        $required = ['Transaction Date', 'Vendor Name', 'Item ID', 'Description', 'Ext. Cost', 'Unit Cost', 'Quantity'];
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray();
        $headers = array_map('trim', $rows[0] ?? []);
        foreach ($required as $col) {
            if (! in_array($col, $headers, true)) {
                throw new \RuntimeException("Inventory file missing required column: {$col}");
            }
        }

        $supplierIds = [];
        $itemIds = [];
        $countPurchases = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $row = array_combine($headers, array_pad(array_slice($rows[$i], 0, count($headers)), count($headers), null));
            $vendorName = trim((string) ($row['Vendor Name'] ?? ''));
            $itemId = trim((string) ($row['Item ID'] ?? ''));
            $extCost = $this->toFloat($row['Ext. Cost'] ?? 0);
            if (! $vendorName || ! $itemId || $extCost <= 0) {
                continue;
            }

            if (! isset($supplierIds[$vendorName])) {
                $supplier = Supplier::create([
                    'name' => $vendorName,
                    'data_import_id' => $dataImportId,
                ]);
                $supplierIds[$vendorName] = $supplier->id;
            }
            if (! isset($itemIds[$itemId])) {
                $item = Item::create([
                    'internal_part_number' => $itemId,
                    'description' => $row['Description'] ?? null,
                    'data_import_id' => $dataImportId,
                ]);
                $itemIds[$itemId] = $item->id;
            }

            $orderDate = $row['Transaction Date'] ?? null;
            $date = $orderDate ? Carbon::parse($orderDate) : null;

            Purchase::create([
                'item_id' => $itemIds[$itemId],
                'supplier_id' => $supplierIds[$vendorName],
                'unit_price' => $this->toFloat($row['Unit Cost'] ?? 0),
                'quantity' => $this->toFloat($row['Quantity'] ?? 0),
                'currency' => 'USD',
                'order_date' => $date,
                'data_import_id' => $dataImportId,
            ]);
            $countPurchases++;
        }

        return [
            'suppliers' => count($supplierIds),
            'items' => count($itemIds),
            'purchases' => $countPurchases,
        ];
    }

    private function parseVendorPriority(string $path, int $dataImportId): int
    {
        $required = ['Vendor Name', 'priority_rank'];
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray();
        $headers = array_map('trim', $rows[0] ?? []);
        foreach ($required as $col) {
            if (! in_array($col, $headers, true)) {
                throw new \RuntimeException("Vendor priority file missing required column: {$col}");
            }
        }

        $count = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $row = array_combine($headers, array_pad(array_slice($rows[$i], 0, count($headers)), count($headers), null));
            $vendorName = trim((string) ($row['Vendor Name'] ?? ''));
            $rank = (int) ($row['priority_rank'] ?? 0);
            if ($vendorName === '') {
                continue;
            }
            VendorPriority::create([
                'data_import_id' => $dataImportId,
                'vendor_name' => $vendorName,
                'priority_rank' => $rank,
            ]);
            $count++;
        }
        return $count;
    }

    private function parseItemSpread(string $path, int $dataImportId): int
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray();
        $headers = array_map('trim', $rows[0] ?? []);
        if (! in_array('Item ID', $headers, true)) {
            throw new \RuntimeException('Item spread file missing required column: Item ID');
        }

        $count = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $row = array_combine($headers, array_pad(array_slice($rows[$i], 0, count($headers)), count($headers), null));
            $partNumber = trim((string) ($row['Item ID'] ?? ''));
            if ($partNumber === '') {
                continue;
            }
            ItemSpread::create([
                'data_import_id' => $dataImportId,
                'internal_part_number' => $partNumber,
            ]);
            $count++;
        }
        return $count;
    }

    private function parseMpnMap(string $path, int $dataImportId): int
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray();
        $headers = array_map('trim', $rows[0] ?? []);
        if (! in_array('Item ID', $headers, true) || ! in_array('mpn', $headers, true)) {
            throw new \RuntimeException('MPN map file must have columns: Item ID, mpn');
        }

        $itemIdToDbId = Item::where('data_import_id', $dataImportId)->pluck('id', 'internal_part_number')->all();
        $count = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $row = array_combine($headers, array_pad(array_slice($rows[$i], 0, count($headers)), count($headers), null));
            $internalPartNumber = trim((string) ($row['Item ID'] ?? ''));
            $mpn = trim((string) ($row['mpn'] ?? ''));
            $lookupMode = strtolower(trim((string) ($row['lookup_mode'] ?? '')));
            $isNonCatalog = in_array($lookupMode, ['non_catalog', 'noncatalog', 'custom'], true);
            if ($internalPartNumber === '') {
                continue;
            }
            $itemId = $itemIdToDbId[$internalPartNumber] ?? null;
            if (! $itemId) {
                continue;
            }
            if ($mpn === '' && ! $isNonCatalog) {
                continue;
            }
            Mapping::create([
                'item_id' => $itemId,
                'mpn' => $mpn !== '' ? $mpn : 'NONCATALOG',
                'manufacturer' => $row['manufacturer'] ?? null,
                'mapping_status' => $isNonCatalog ? 'non_catalog' : ($row['mapping_status'] ?? 'mapped'),
                'lookup_mode' => $isNonCatalog ? 'non_catalog' : ($row['lookup_mode'] ?? null),
                'data_import_id' => $dataImportId,
            ]);
            $count++;
        }
        return $count;
    }

    private function toFloat(mixed $v): float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        return (float) preg_replace('/[^0-9.-]/', '', (string) $v) ?: 0.0;
    }
}
