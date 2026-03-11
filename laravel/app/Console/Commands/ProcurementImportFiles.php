<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImportJob;
use App\Models\DataImport;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProcurementImportFiles extends Command
{
    protected $signature = 'procurement:import-files
                            {inventory : Path to inventory Excel/CSV file (columns A–V + Mfg Part Number 1–5)}';

    protected $description = 'Import inventory file from local path. Populates inventories and mpn tables (replaces previous full import).';

    public function handle(AuditLogService $auditLog): int
    {
        $inventory = $this->argument('inventory');
        $resolved = $this->resolvePath($inventory);
        if (! is_file($resolved)) {
            $this->error("File not found: {$inventory}");
            return self::FAILURE;
        }

        $dir = 'imports/cli_'.now()->format('Y-m-d_His');
        $ext = $this->extension($inventory);
        $inventoryPath = $dir.'/inventory.'.$ext;

        $this->info('Copying file into storage...');
        Storage::disk('imports')->makeDirectory($dir);
        $fullPath = Storage::disk('imports')->path($inventoryPath);
        File::ensureDirectoryExists(dirname($fullPath));
        copy($resolved, $fullPath);

        $import = DataImport::create([
            'type' => 'full',
            'user_id' => null,
            'file_names' => [
                'inventory' => basename($inventory),
            ],
            'row_counts' => [],
            'status' => 'pending',
        ]);
        $auditLog->log('import.created', null, 'data_import', $import->id, [
            'files' => $import->file_names,
        ]);

        $this->info('Running import (sync)...');
        ProcessImportJob::dispatchSync($import, $inventoryPath);

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
