<?php

namespace App\Services;

use App\DTO\PriceFindingData;
use Illuminate\Support\Facades\Http;

class MouserClient
{
    public function __construct(
        protected array $config
    ) {
        $this->config = array_merge(config('procurement.mouser', []), $config);
    }

    public static function fromConfig(): self
    {
        return new self(config('procurement.mouser', []));
    }

    public function isEnabled(): bool
    {
        return ! empty($this->config['api_key']);
    }

    /**
     * Search by part number. Returns up to 3 normalized findings.
     *
     * @return array<int, PriceFindingData>
     */
    public function lookup(string $queryMpn): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $url = $this->config['search_url'] ?? '';
        $url = str_contains($url, '?') ? $url . '&apiKey=' . urlencode($this->config['api_key']) : $url . '?apiKey=' . urlencode($this->config['api_key']);

        $response = Http::timeout(20)
            ->post($url, [
                'SearchByPartRequest' => [
                    'mouserPartNumber' => $queryMpn,
                    'partSearchOptions' => 'None',
                ],
            ]);

        if ($response->failed()) {
            return [];
        }

        $payload = $response->json();
        $parts = $payload['SearchResults']['Parts'] ?? [];
        if (! is_array($parts)) {
            return [];
        }

        $findings = [];
        foreach (array_slice($parts, 0, 3) as $part) {
            if (! is_array($part)) {
                continue;
            }
            $priceBreaks = $this->parsePriceBreaks($part['PriceBreaks'] ?? []);
            $minPrice = $this->minPriceFromBreaks($priceBreaks);

            $findings[] = new PriceFindingData(
                provider: 'mouser',
                currency: $minPrice !== null ? 'USD' : null,
                priceBreaks: $priceBreaks,
                minUnitPrice: $minPrice
            );
        }

        return $findings;
    }

    /**
     * @param  array<int, mixed>  $priceBreaks
     * @return array<int, array{qty: int, price: float}>
     */
    protected function parsePriceBreaks(array $priceBreaks): array
    {
        $out = [];
        foreach ($priceBreaks as $row) {
            if (! is_array($row)) {
                continue;
            }
            $qty = (int) ($row['Quantity'] ?? $row['quantity'] ?? $row['qty'] ?? 0);
            $price = $this->toFloat($row['Price'] ?? $row['price'] ?? $row['UnitPrice'] ?? null);
            if ($qty > 0 && $price !== null) {
                $out[] = ['qty' => $qty, 'price' => $price];
            }
        }
        return $out;
    }

    protected function minPriceFromBreaks(array $breaks): ?float
    {
        $prices = array_column($breaks, 'price');
        return $prices !== [] ? min($prices) : null;
    }

    protected function toFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $cleaned = str_replace([',', '$'], '', trim($value));
            return $cleaned !== '' && is_numeric($cleaned) ? (float) $cleaned : null;
        }
        return null;
    }
}
