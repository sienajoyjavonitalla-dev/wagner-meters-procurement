<?php

namespace App\Services;

use App\DTO\PriceFindingData;
use App\Models\ResearchedMpn;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Element14Client
{
    public function __construct(
        protected array $config
    ) {
        $this->config = array_merge(config('procurement.element14', []), $config);
    }

    public static function fromConfig(): self
    {
        return new self(config('procurement.element14', []));
    }

    public function isEnabled(): bool
    {
        return ! empty($this->config['api_key']);
    }

    /**
     * Look up product by manufacturer part number via element14/Newark Product Search API.
     * Returns an array of PriceFindingData (normally 0 or 1 item).
     *
     * @param  int|null  $inventoryId  Optional. When set, logs \"item_id X: Element14 API\" for tailing.
     * @return array<int, PriceFindingData>
     */
    public function lookup(string $queryMpn, ?int $inventoryId = null): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $cacheKey = $queryMpn;
        $cached = ResearchedMpn::getCached($cacheKey, ResearchedMpn::SOURCE_ELEMENT14);
        if ($cached !== null) {
            if ($inventoryId !== null) {
                Log::info('item_id '.$inventoryId.': Element14 API (researched_mpn)');
            }
            return $this->findingsFromPayload($cached);
        }

        if ($inventoryId !== null) {
            Log::info('item_id '.$inventoryId.': Element14 API (direct)');
        }

        $base = rtrim($this->config['base_url'] ?? 'https://partner.element14.com', '/');
        $version = $this->config['version'] ?? '1.4';
        $storeId = $this->config['store_id'] ?? 'www.newark.com';
        $numberOfResults = (int) ($this->config['number_of_results'] ?? 1);
        $filters = $this->config['refinements_filters'] ?? 'rohsCompliant,inStock';
        $responseGroup = $this->config['response_group'] ?? 'large';

        $url = $base.'/catalog/products';
        $query = [
            'versionNumber' => $version,
            // Search by manufacturer part number
            'term' => 'manuPartNum:'.$queryMpn,
            'storeInfo.id' => $storeId,
            'resultsSettings.offset' => 0,
            'resultsSettings.numberOfResults' => $numberOfResults,
            'resultsSettings.refinements.filters' => $filters,
            'resultsSettings.responseGroup' => $responseGroup,
            'callInfo.omitXmlSchema' => 'false',
            'callInfo.callback' => '',
            'callInfo.responseDataFormat' => 'json',
            'callInfo.apiKey' => $this->config['api_key'],
        ];

        $response = Http::timeout(20)->get($url, $query);
        if ($response->failed()) {
            return [];
        }

        $payload = $response->json();
        if (is_array($payload)) {
            $firstProduct = $this->firstProductFromPayload($payload);
            $productUrl = is_array($firstProduct) ? $this->extractProductUrl($firstProduct) : null;
            ResearchedMpn::setCached($cacheKey, ResearchedMpn::SOURCE_ELEMENT14, $payload, $productUrl);
        }

        return $this->findingsFromPayload($payload ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, PriceFindingData>
     */
    protected function findingsFromPayload(array $payload): array
    {
        $product = $this->firstProductFromPayload($payload);
        if (! is_array($product)) {
            return [];
        }

        $priceBreaks = $this->parsePriceBreaks($product['prices'] ?? []);
        $minPrice = $this->minPriceFromBreaks($priceBreaks);

        $matchedMpn = trim((string) (
            $product['translatedManufacturerPartNumber'] ?? $product['manufacturerPartNumber'] ?? $product['manuPartNum'] ?? ''
        )) ?: null;

        $productUrl = $this->extractProductUrl($product);

        // Newark US store uses USD; if you later support other stores, this can be made configurable.
        $currency = $minPrice !== null ? 'USD' : null;

        $finding = new PriceFindingData(
            provider: 'element14',
            currency: $currency,
            priceBreaks: $priceBreaks,
            minUnitPrice: $minPrice,
            matchedMpn: $matchedMpn,
            productUrl: $productUrl
        );

        return [$finding];
    }

    /**
     * Extract first product from any of the known element14 response envelopes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function firstProductFromPayload(array $payload): ?array
    {
        $candidates = [
            $payload['manufacturerPartNumberSearchReturn']['products'] ?? null,
            $payload['keywordSearchReturn']['products'] ?? null,
            $payload['products'] ?? null,
        ];

        foreach ($candidates as $products) {
            if (is_array($products) && isset($products[0]) && is_array($products[0])) {
                return $products[0];
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $prices
     * @return array<int, array{qty: int, price: float}>
     */
    protected function parsePriceBreaks(array $prices): array
    {
        $out = [];
        foreach ($prices as $row) {
            if (! is_array($row)) {
                continue;
            }
            $from = (int) ($row['from'] ?? $row['fromQuantity'] ?? $row['qty'] ?? 0);
            $cost = $this->toFloat($row['cost'] ?? $row['price'] ?? null);
            if ($from > 0 && $cost !== null) {
                $out[] = ['qty' => $from, 'price' => $cost];
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

    /**
     * Try to extract a useful product URL from the product payload.
     *
     * @param  array<string, mixed>  $product
     */
    protected function extractProductUrl(array $product): ?string
    {
        $candidates = [
            $product['productUrl'] ?? null,
            $product['translatedProductUrl'] ?? null,
            $product['dataSheetUrl'] ?? null,
            $product['datasheetUrl'] ?? null,
            $product['url'] ?? null,
        ];

        foreach ($candidates as $url) {
            if (is_string($url) && trim($url) !== '') {
                return trim($url);
            }
        }

        return null;
    }
}

