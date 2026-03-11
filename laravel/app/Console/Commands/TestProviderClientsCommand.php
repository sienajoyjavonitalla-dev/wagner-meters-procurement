<?php

namespace App\Console\Commands;

use App\Services\GeminiResearchService;
use Illuminate\Console\Command;

class TestProviderClientsCommand extends Command
{
    protected $signature = 'procurement:test-providers
                            {--mpn= : Optional MPN to look up (e.g. 1N4148 or STM32F103C8T6)}';

    protected $description = 'Check Gemini API config and optionally run a part lookup';

    public function handle(GeminiResearchService $gemini): int
    {
        $this->info('Provider status (credentials from .env / config/procurement.php):');
        $this->table(
            ['Provider', 'Enabled', 'Note'],
            [
                ['Gemini', $gemini->isEnabled() ? 'Yes' : 'No', $gemini->isEnabled() ? 'GEMINI_API_KEY set' : 'Set GEMINI_API_KEY in .env'],
            ]
        );

        $mpn = $this->option('mpn');
        if (! $mpn) {
            $this->newLine();
            $this->comment('To test a real lookup, run: php artisan procurement:test-providers --mpn=1N4148');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Lookup MPN: {$mpn}");
        if (! $gemini->isEnabled()) {
            $this->warn('Gemini is not configured. Set GEMINI_API_KEY.');
            return self::FAILURE;
        }

        try {
            $result = $gemini->lookup('Test Vendor', 'Test Line', [$mpn], 1.0);
            if ($result['success'] ?? false) {
                $price = $result['current_vendor_price'] ?? null;
                $alt = $result['alt_vendors'] ?? [];
                $this->line('  Current vendor price: ' . ($price !== null ? number_format($price, 4) . ' USD' : '—'));
                $this->line('  Alternative vendors: ' . count($alt));
                foreach (array_slice($alt, 0, 3) as $a) {
                    $this->line('    - ' . ($a['vendor_name'] ?? '?') . ': ' . number_format($a['unit_price'] ?? 0, 4));
                }
            } else {
                $this->line('  Result: ' . ($result['error'] ?? 'unknown error'));
            }
        } catch (\Throwable $e) {
            $this->error('  Error: ' . $e->getMessage());
        }

        $this->newLine();
        return self::SUCCESS;
    }
}
