<?php

namespace App\Services;

use App\DTO\PriceFindingData;
use App\Models\ResearchedMpn;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * @param  int|null  $inventoryId  Optional. When set, logs "item_id X: Mouser API" when making the request (for tail).
     * @return array<int, PriceFindingData>
     */
    public function lookup(string $queryMpn, ?int $inventoryId = null): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $cached = ResearchedMpn::getCached($queryMpn, ResearchedMpn::SOURCE_MOUSER);
        if ($cached !== null) {
            if ($inventoryId !== null) {
                Log::info("item_id {$inventoryId}: Mouser API (researched_mpn)");
            }
            return $this->findingsFromPayload($cached);
        }

        if ($inventoryId !== null) {
            Log::info("item_id {$inventoryId}: Mouser API (direct)");
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
        if (is_array($payload)) {
            $parts = $payload['SearchResults']['Parts'] ?? [];
            $firstPart = is_array($parts) && isset($parts[0]) ? $parts[0] : null;
            $url = is_array($firstPart) ? $this->extractProductUrl($firstPart) : null;
            ResearchedMpn::setCached($queryMpn, ResearchedMpn::SOURCE_MOUSER, $payload, $url);
        }

        return $this->findingsFromPayload($payload ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload  Raw Mouser API response
     * @return array<int, PriceFindingData>
     */
    protected function findingsFromPayload(array $payload): array
    {
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
            $matchedMpn = trim((string) ($part['ManufacturerPartNumber'] ?? $part['MouserPartNumber'] ?? '')) ?: null;
            $productUrl = $this->extractProductUrl($part);
            $findings[] = new PriceFindingData(
                provider: 'mouser',
                currency: $minPrice !== null ? 'USD' : null,
                priceBreaks: $priceBreaks,
                minUnitPrice: $minPrice,
                matchedMpn: $matchedMpn,
                productUrl: $productUrl
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

    protected function extractProductUrl(array $part): ?string
    {
        $url = $part['ProductDetailUrl'] ?? $part['ProductUrl'] ?? $part['DataSheetUrl'] ?? $part['Url'] ?? null;
        if (is_string($url) && trim($url) !== '') {
            return trim($url);
        }
        return null;
    }
}
