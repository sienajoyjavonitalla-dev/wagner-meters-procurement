<?php

namespace App\Jobs;

use App\Models\DataImport;
use App\Models\Inventory;
use App\Models\Mpn;
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
        public string $inventoryPath
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
                Inventory::where('data_import_id', $previousId)->delete();
            }

            $disk = Storage::disk('imports');
            $path = $this->normalizePath($disk->path($this->inventoryPath));
            $rowCounts = $this->parseInventoryFile($path, $import->id);

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

    /**
     * Sheet columns A–V → inventories; W–AA (Mfg Part Number 1–5) → mpn (one row per non-empty value).
     *
     * @return array{inventories_count: int, mpn_count: int}
     */
    private function parseInventoryFile(string $path, int $dataImportId): array
    {
        $required = ['Transaction Date', 'Item ID', 'Description', 'Unit Cost', 'Ext. Cost', 'Quantity', 'Vendor Name', 'Product Line'];
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray();
        $headers = array_map('trim', $rows[0] ?? []);
        foreach ($required as $col) {
            if (! in_array($col, $headers, true)) {
                throw new \RuntimeException("Inventory file missing required column: {$col}");
            }
        }

        $mpnColumns = ['Mfg Part Number 1', 'Mfg Part Number 2', 'Mfg Part Number 3', 'Mfg Part Number 4', 'Mfg Part Number 5'];
        $inventoriesCount = 0;
        $mpnCount = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $row = array_combine($headers, array_pad(array_slice($rows[$i], 0, count($headers)), count($headers), null));
            $itemId = trim((string) ($row['Item ID'] ?? ''));
            if ($itemId === '') {
                continue;
            }

            $transactionDate = isset($row['Transaction Date']) && (string) $row['Transaction Date'] !== ''
                ? Carbon::parse($row['Transaction Date'])->format('Y-m-d')
                : null;

            $inventory = Inventory::create([
                'data_import_id' => $dataImportId,
                'transaction_date' => $transactionDate,
                'item_id' => $itemId,
                'description' => $this->trim($row['Description'] ?? null),
                'fiscal_period' => $this->trim($row['Fiscal Period'] ?? null),
                'fiscal_year' => $this->trim($row['Fiscal Year'] ?? null),
                'reference_id' => $this->trim($row['Reference ID'] ?? null),
                'location_id' => $this->trim($row['Location ID'] ?? null),
                'source_id' => $this->trim($row['Source ID'] ?? null),
                'type' => $this->trim($row['Type'] ?? null),
                'application_id' => $this->trim($row['Application ID'] ?? null),
                'unit' => $this->trim($row['Unit'] ?? null),
                'quantity' => $this->toFloat($row['Quantity'] ?? null),
                'unit_cost' => $this->toFloat($row['Unit Cost'] ?? null),
                'ext_cost' => $this->toFloat($row['Ext. Cost'] ?? null),
                'comments' => $this->trim($row['Comments'] ?? null),
                'product_line' => $this->trim($row['Product Line'] ?? null),
                'vendor_name' => $this->trim($row['Vendor Name'] ?? null),
                'contact' => $this->trim($row['Contact'] ?? null),
                'address' => $this->trim($row['Address'] ?? null),
                'region' => $this->trim($row['Region'] ?? null),
                'phone' => $this->trim($row['Phone'] ?? null),
                'email' => $this->trim($row['Email'] ?? null),
            ]);
            $inventoriesCount++;

            foreach ($mpnColumns as $col) {
                $partNumber = isset($row[$col]) ? trim((string) $row[$col]) : '';
                if ($partNumber !== '') {
                    Mpn::create([
                        'inventory_id' => $inventory->id,
                        'part_number' => $partNumber,
                    ]);
                    $mpnCount++;
                }
            }
        }

        return [
            'inventories_count' => $inventoriesCount,
            'mpn_count' => $mpnCount,
        ];
    }

    private function trim(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private function toFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $v);
        return $cleaned !== '' && is_numeric($cleaned) ? (float) $cleaned : null;
    }
}
