<?php

namespace App\Services;

use App\Models\FxSnapshot;
use Illuminate\Support\Facades\Http;

class FxSnapshotService
{
    protected string $url = 'https://open.er-api.com/v6/latest/USD';

    /**
     * Fetch USD rates and store in fx_snapshots. Returns stored rates or empty array on failure.
     *
     * @return array<string, float>
     */
    public function capture(): array
    {
        try {
            $response = Http::timeout(15)->get($this->url);
            if ($response->failed()) {
                return [];
            }
            $data = $response->json();
            $rates = $data['rates'] ?? [];
            if (! is_array($rates)) {
                return [];
            }
            $filtered = [];
            foreach ($rates as $ccy => $value) {
                if (is_numeric($value)) {
                    $filtered[$ccy] = (float) $value;
                }
            }
            FxSnapshot::query()->updateOrInsert(
                ['key' => 'fx_rates'],
                ['value_json' => ['base' => 'USD', 'rates' => $filtered]]
            );
            return $filtered;
        } catch (\Throwable) {
            return [];
        }
    }
}
