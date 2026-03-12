<?php

namespace App\Services;

use App\DTO\PriceFindingData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DigiKeyClient
{
    protected ?string $token = null;

    public function __construct(
        protected array $config
    ) {
        $this->config = array_merge(config('procurement.digikey', []), $config);
    }

    public static function fromConfig(): self
    {
        return new self(config('procurement.digikey', []));
    }

    public function isEnabled(): bool
    {
        return ! empty($this->config['client_id']) && ! empty($this->config['client_secret']);
    }

    /**
     * Look up product by manufacturer part number. Returns one normalized finding or empty array.
     * When DigiKey returns 404 "Duplicate Products", pass manufacturerId (DigiKey's manufacturer ID) to disambiguate.
     *
     * @param  int|null  $manufacturerId  Optional. Use when part number matches multiple products (404 response asks for it).
     * @return array<int, PriceFindingData>
     */
    public function lookup(string $queryMpn, ?int $manufacturerId = null): array
    {
        $token = $this->getToken();
        if (! $token) {
            Log::warning('DigiKey lookup skipped: failed to obtain access token.', ['mpn' => $queryMpn]);
            return [];
        }

        $url = str_replace(
            '{part_number}',
            rawurlencode($queryMpn),
            $this->config['product_url'] ?? ''
        );
        if ($manufacturerId !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'manufacturerId=' . (int) $manufacturerId;
        }

        Log::info('DigiKey API request for MPN: '.$queryMpn.($manufacturerId !== null ? " (manufacturerId={$manufacturerId})" : ''));

        $response = Http::withHeaders($this->headers($token))
            ->timeout(20)
            ->get($url);

        if ($response->failed()) {
            $body = $response->body();
            $decoded = $response->json();
            $detail = is_array($decoded) ? ($decoded['detail'] ?? '') : '';
            $isDuplicate = $response->status() === 404
                && (str_contains((string) $detail, 'Duplicate') || str_contains((string) $detail, 'manufacturerId'));

            if ($isDuplicate) {
                Log::warning('DigiKey returned 404: multiple products match this part number. Not an API key issue. Add manufacturerId to disambiguate (e.g. from DigiKey Manufacturers API or your data).', [
                    'mpn' => $queryMpn,
                    'status' => 404,
                    'detail' => $detail,
                ]);
            } else {
                Log::warning('DigiKey lookup failed.', [
                    'mpn' => $queryMpn,
                    'status' => $response->status(),
                    'body' => mb_substr($body, 0, 500),
                ]);
            }
            return [];
        }

        $payload = $response->json();
        Log::info('DigiKey API response', ['mpn' => $queryMpn, 'response' => $payload]);

        $product = $payload['Product'] ?? null;
        if (! is_array($product)) {
            return [];
        }

        $price = $this->parseProductPrice($product);
        $priceBreaks = $this->extractPriceBreaks($product);
        $matchedMpn = trim((string) (
            $product['ManufacturerProductNumber']
            ?? $product['ManufacturerPartNumber']
            ?? $product['DigiKeyProductNumber']
            ?? ''
        )) ?: null;
        $productUrl = $this->extractProductUrl($product);

        $currency = $this->config['locale_currency'] ?? 'USD';
        $finding = new PriceFindingData(
            provider: 'digikey',
            currency: $price !== null ? $currency : null,
            priceBreaks: $priceBreaks,
            minUnitPrice: $price,
            matchedMpn: $matchedMpn,
            productUrl: $productUrl
        );

        return [$finding];
    }

    protected function getToken(): ?string
    {
        if ($this->token !== null) {
            return $this->token;
        }
        if (! $this->isEnabled()) {
            return null;
        }

        $response = Http::asForm()
            ->timeout(20)
            ->post($this->config['token_url'] ?? '', [
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            Log::warning('DigiKey token request failed.', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            return null;
        }

        $this->token = $response->json('access_token');
        return $this->token;
    }

    protected function headers(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-DIGIKEY-Client-Id' => $this->config['client_id'] ?? '',
            'Accept' => 'application/json',
            'X-DIGIKEY-Locale-Site' => $this->config['locale_site'] ?? 'US',
            'X-DIGIKEY-Locale-Language' => $this->config['locale_language'] ?? 'en',
            'X-DIGIKEY-Locale-Currency' => $this->config['locale_currency'] ?? 'USD',
            'X-DIGIKEY-Account-Id' => $this->config['account_id'] ?? '0',
        ];
    }

    protected function parseProductPrice(array $product): ?float
    {
        $unit = $this->toFloat($product['UnitPrice'] ?? null);
        if ($unit !== null) {
            return $unit;
        }

        $variations = $product['ProductVariations'] ?? [];
        if (! is_array($variations)) {
            return null;
        }

        $candidates = [];
        foreach ($variations as $var) {
            if (! is_array($var)) {
                continue;
            }
            $min = $this->minPriceFromBreaks($var['StandardPricing'] ?? []);
            if ($min !== null) {
                $candidates[] = $min;
            }
        }

        return $candidates !== [] ? min($candidates) : null;
    }

    /**
     * @return array<int, array{qty: int, price: float}>
     */
    protected function extractPriceBreaks(array $product): array
    {
        $breaks = [];
        $variations = $product['ProductVariations'] ?? [];
        if (! is_array($variations)) {
            return $breaks;
        }
        foreach ($variations as $var) {
            if (! is_array($var)) {
                continue;
            }
            foreach ((array) ($var['StandardPricing'] ?? []) as $row) {
                $qty = isset($row['BreakQuantity']) ? (int) $row['BreakQuantity'] : (int) ($row['Quantity'] ?? $row['qty'] ?? 0);
                $price = $this->toFloat($row['UnitPrice'] ?? $row['Price'] ?? $row['price'] ?? null);
                if ($qty > 0 && $price !== null) {
                    $breaks[] = ['qty' => $qty, 'price' => $price];
                }
            }
        }
        return $breaks;
    }

    protected function minPriceFromBreaks(array $standardPricing): ?float
    {
        $prices = [];
        foreach ((array) $standardPricing as $row) {
            $p = $this->toFloat($row['Price'] ?? $row['price'] ?? $row['UnitPrice'] ?? null);
            if ($p !== null) {
                $prices[] = $p;
            }
        }
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

    protected function extractProductUrl(array $product): ?string
    {
        $url = $product['ProductUrl'] ?? $product['DetailUrl'] ?? $product['PrimaryPhoto'] ?? $product['ProductLink'] ?? $product['Url'] ?? null;
        if (is_string($url) && trim($url) !== '') {
            return trim($url);
        }
        return null;
    }
}
