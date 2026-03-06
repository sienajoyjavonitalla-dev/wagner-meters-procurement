<?php

namespace App\Console\Commands;

use App\Services\ClaudeResearchService;
use App\Services\DigiKeyClient;
use App\Services\MouserClient;
use App\Services\NexarClient;
use Illuminate\Console\Command;

class TestProviderClientsCommand extends Command
{
    protected $signature = 'procurement:test-providers
                            {--mpn= : Optional MPN to look up (e.g. 1N4148 or STM32F103C8T6)}';

    protected $description = 'Check provider API config and optionally run a part lookup';

    public function handle(
        ClaudeResearchService $claude,
        DigiKeyClient $digiKey,
        MouserClient $mouser,
        NexarClient $nexar
    ): int {
        $this->info('Provider status (credentials from .env / config/procurement.php):');
        $this->table(
            ['Provider', 'Enabled', 'Note'],
            [
                ['DigiKey', $digiKey->isEnabled() ? 'Yes' : 'No', $digiKey->isEnabled() ? 'DIGIKEY_CLIENT_ID + CLIENT_SECRET' : 'Set DIGIKEY_CLIENT_ID and DIGIKEY_CLIENT_SECRET'],
                ['Mouser', $mouser->isEnabled() ? 'Yes' : 'No', $mouser->isEnabled() ? 'MOUSER_API_KEY' : 'Set MOUSER_API_KEY'],
                ['Nexar', $nexar->isEnabled() ? 'Yes' : 'No', $nexar->isEnabled() ? 'NEXAR_CLIENT_ID + CLIENT_SECRET' : 'Set NEXAR_CLIENT_ID and NEXAR_CLIENT_SECRET'],
                ['Claude', $claude->isEnabled() ? 'Yes' : 'No', $claude->isEnabled() ? 'ANTHROPIC_API_KEY' : 'Set ANTHROPIC_API_KEY for AI fallback'],
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
        $this->newLine();

        $this->runCatalogLookup($digiKey, $mouser, $nexar, $mpn);
        $this->runClaudeLookup($claude, $mpn);

        $this->newLine();
        return self::SUCCESS;
    }

    private function runCatalogLookup(DigiKeyClient $digiKey, MouserClient $mouser, NexarClient $nexar, string $mpn): void
    {
        $catalog = [
            'DigiKey' => $digiKey,
            'Mouser' => $mouser,
            'Nexar' => $nexar,
        ];
        foreach ($catalog as $name => $client) {
            if (! $client->isEnabled()) {
                $this->line("  [{$name}] skipped (not configured)");
                continue;
            }
            try {
                $findings = $client->lookup($mpn);
                $this->reportFindings($name, $findings);
            } catch (\Throwable $e) {
                $this->line("  [{$name}] error: " . $e->getMessage());
            }
        }
    }

    private function runClaudeLookup(ClaudeResearchService $claude, string $mpn): void
    {
        if (! $claude->isEnabled()) {
            $this->line("  [Claude] skipped (not configured)");
            return;
        }
        try {
            $result = $claude->debugLookup('Test Vendor', 'TEST-001', 'Test part for MPN lookup', $mpn);
            $this->reportFindings('Claude', $result['findings']);

            if (count($result['findings']) === 0 && is_string($result['error'] ?? null)) {
                $status = $result['http_status'] ?? null;
                $statusText = $status ? "HTTP {$status}" : "no HTTP status";
                $this->line("    - reason: {$result['error']} ({$statusText})");

                $raw = $result['raw_text'] ?? null;
                if (is_string($raw) && trim($raw) !== '') {
                    $preview = mb_substr(trim($raw), 0, 280);
                    $this->line('    - response preview: ' . $preview);
                }
            }
        } catch (\Throwable $e) {
            $this->line("  [Claude] error: " . $e->getMessage());
        }
    }

    private function reportFindings(string $name, array $findings): void
    {
        $count = count($findings);
        if ($count === 0) {
            $this->line("  [{$name}] no results");
            return;
        }
        $min = null;
        $currency = null;
        foreach ($findings as $f) {
            if ($f->minUnitPrice !== null && ($min === null || $f->minUnitPrice < $min)) {
                $min = $f->minUnitPrice;
                $currency = $f->currency;
            }
        }
        $priceStr = $min !== null ? sprintf('%s %s', $currency ?? 'USD', number_format($min, 4)) : 'no price';
        $this->line("  [{$name}] {$count} finding(s), min unit price: {$priceStr}");
    }
}
