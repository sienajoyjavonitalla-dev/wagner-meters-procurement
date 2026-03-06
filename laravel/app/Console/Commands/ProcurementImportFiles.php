<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImportJob;
use App\Models\DataImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcurementImportFiles extends Command
{
    protected $signature = 'procurement:import-files
                            {inventory : Path to inventory Excel file}
                            {vendor_priority : Path to vendor priority CSV/Excel}
                            {item_spread : Path to item spread CSV/Excel}
                            {--mpn-map= : Optional path to MPN map CSV/Excel}';

    protected $description = 'One-time import: load inventory, vendor priority, item spread, and optional MPN map from local paths (snapshot: replaces previous full import).';

    public function handle(): int
    {
        $inventory = $this->argument('inventory');
        $vendorPriority = $this->argument('vendor_priority');
        $itemSpread = $this->argument('item_spread');
        $mpnMap = $this->option('mpn-map');

        foreach (['inventory' => $inventory, 'vendor_priority' => $vendorPriority, 'item_spread' => $itemSpread] as $name => $path) {
            $resolved = $this->resolvePath($path);
            if (! is_file($resolved)) {
                $this->error("File not found ({$name}): {$path}");
                return self::FAILURE;
            }
        }
        if ($mpnMap !== null && $mpnMap !== '') {
            $resolved = $this->resolvePath($mpnMap);
            if (! is_file($resolved)) {
                $this->error('File not found (mpn-map): '.$mpnMap);
                return self::FAILURE;
            }
        }

        $dir = 'imports/cli_'.now()->format('Y-m-d_His');
        $inventoryPath = $dir.'/inventory.'.$this->extension($inventory);
        $vendorPriorityPath = $dir.'/vendor_priority.'.$this->extension($vendorPriority);
        $itemSpreadPath = $dir.'/item_spread.'.$this->extension($itemSpread);
        $mpnMapPath = $mpnMap ? $dir.'/mpn_map.'.$this->extension($mpnMap) : null;

        $this->info('Copying files into storage...');
        Storage::makeDirectory($dir);
        copy($this->resolvePath($inventory), Storage::path($inventoryPath));
        copy($this->resolvePath($vendorPriority), Storage::path($vendorPriorityPath));
        copy($this->resolvePath($itemSpread), Storage::path($itemSpreadPath));
        if ($mpnMapPath !== null) {
            copy($this->resolvePath($mpnMap), Storage::path($mpnMapPath));
        }

        $import = DataImport::create([
            'type' => 'full',
            'user_id' => null,
            'file_names' => [
                'inventory' => basename($inventory),
                'vendor_priority' => basename($vendorPriority),
                'item_spread' => basename($itemSpread),
                'mpn_map' => $mpnMap ? basename($mpnMap) : null,
            ],
            'row_counts' => [],
            'status' => 'pending',
        ]);

        $this->info('Running import (sync)...');
        ProcessImportJob::dispatchSync($import, $inventoryPath, $vendorPriorityPath, $itemSpreadPath, $mpnMapPath);

        $import->refresh();
        if ($import->status === 'completed') {
            $this->info('Import completed. Row counts: '.json_encode($import->row_counts, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }
        $this->error('Import failed. Check logs.');
        return self::FAILURE;
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);
        if (str_starts_with($path, '/') || (strlen($path) >= 2 && $path[1] === ':')) {
            return $path;
        }

        return base_path($path);
    }

    private function extension(string $path): string
    {
        $base = basename($path);
        $pos = strrpos($base, '.');
        if ($pos === false) {
            return 'xlsx';
        }

        return strtolower(substr($base, $pos + 1));
    }
}
