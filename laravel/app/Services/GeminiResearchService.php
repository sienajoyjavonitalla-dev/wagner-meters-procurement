<?php

namespace App\Services;

use App\Models\AltVendor;
use App\Models\Inventory;
use App\Models\Mpn;
use Illuminate\Support\Facades\Http;

class GeminiResearchService
{
    public function __construct(
        protected array $config
    ) {
        $this->config = array_merge(config('procurement.gemini', []), $config);
    }

    public static function fromConfig(): self
    {
        return new self(config('procurement.gemini', []));
    }

    public function isEnabled(): bool
    {
        return ! empty($this->config['api_key']);
    }

    /**
     * Look up current vendor price and alternative US vendors for an item.
     *
     * @param  array<int, string>  $mpns  Manufacturing part numbers (e.g. from inventory's mpn rows)
     * @return array{
     *   success: bool,
     *   current_vendor_price: float|null,
     *   current_vendor_url: string|null,
     *   current_vendor_currency: string|null,
     *   alt_vendors: array<int, array{vendor_name: string, unit_price: float, url: string|null}>,
     *   error: string|null,
     *   raw_text: string|null
     * }
     */
    public function lookup(string $vendorName, string $productLine, array $mpns, float $quantity): array
    {
        $emptyResult = [
            'success' => false,
            'current_vendor_price' => null,
            'current_vendor_url' => null,
            'current_vendor_currency' => null,
            'alt_vendors' => [],
            'error' => null,
            'raw_text' => null,
        ];

        if (! $this->isEnabled()) {
            $emptyResult['error'] = 'Gemini disabled: missing GEMINI_API_KEY';

            return $emptyResult;
        }

        $prompt = $this->buildPrompt($vendorName, $productLine, $mpns, $quantity);
        $url = $this->buildUrl();

        $response = Http::timeout(60)
            ->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => $this->config['max_output_tokens'] ?? 2048,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            $json = $response->json();
            $err = is_array($json) && isset($json['error']['message'])
                ? $json['error']['message']
                : 'Gemini API request failed (HTTP '.$response->status().')';
            $emptyResult['error'] = $err;

            return $emptyResult;
        }

        $body = $response->json();
        $text = $this->extractText($body);
        if ($text === null || $text === '') {
            $emptyResult['error'] = 'Gemini response did not include any text content';

            return $emptyResult;
        }

        $parsed = $this->parseJsonResponse($text);
        if (! is_array($parsed)) {
            $emptyResult['error'] = 'Gemini response was not valid JSON';
            $emptyResult['raw_text'] = $text;

            return $emptyResult;
        }

        $currentPrice = $this->toFloat($parsed['current_vendor_price'] ?? null);
        $currentUrl = isset($parsed['current_vendor_url']) && trim((string) $parsed['current_vendor_url']) !== ''
            ? trim((string) $parsed['current_vendor_url'])
            : null;
        $currency = isset($parsed['current_vendor_currency']) && trim((string) $parsed['current_vendor_currency']) !== ''
            ? trim((string) $parsed['current_vendor_currency'])
            : 'USD';

        $altVendors = [];
        $rawAlts = $parsed['alt_vendors'] ?? [];
        if (is_array($rawAlts)) {
            foreach ($rawAlts as $alt) {
                if (! is_array($alt)) {
                    continue;
                }
                $name = trim((string) ($alt['vendor_name'] ?? ''));
                $price = $this->toFloat($alt['unit_price'] ?? null);
                if ($name !== '' && $price !== null) {
                    $altVendors[] = [
                        'vendor_name' => $name,
                        'unit_price' => $price,
                        'url' => isset($alt['url']) && trim((string) $alt['url']) !== '' ? trim((string) $alt['url']) : null,
                    ];
                }
            }
        }

        return [
            'success' => true,
            'current_vendor_price' => $currentPrice,
            'current_vendor_url' => $currentUrl,
            'current_vendor_currency' => $currency,
            'alt_vendors' => $altVendors,
            'error' => null,
            'raw_text' => $text,
        ];
    }

    protected function buildPrompt(string $vendorName, string $productLine, array $mpns, float $quantity): string
    {
        $mpnList = array_filter(array_map('trim', $mpns));
        $mpnBlock = empty($mpnList)
            ? 'No part numbers provided.'
            : 'Manufacturing part numbers: '.implode(', ', $mpnList);

        return <<<PROMPT
You are a procurement research assistant. Answer with valid JSON only, no other text.

What is the price of this item from the given vendor? If the item is from another vendor, also search and show the price along with the link. Show only US-based vendors.

Context:
- vendor: {$vendorName}
- product line: {$productLine}
- quantity: {$quantity}
- {$mpnBlock}

Return a single JSON object with exactly these keys:
- "current_vendor_price" (number or null): unit price from {$vendorName} for this item, or null if not found
- "current_vendor_url" (string or null): product page URL from the current vendor if available
- "current_vendor_currency" (string): e.g. "USD"
- "alt_vendors" (array): list of US-based alternative vendors, each with "vendor_name" (string), "unit_price" (number), "url" (string or null). Include only vendors that stock this item. Sort by lowest price first.

Return only the JSON object.
PROMPT;
    }

    protected function buildUrl(): string
    {
        $base = rtrim($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta', '/');
        $model = $this->config['model'] ?? 'gemini-1.5-flash';
        $key = $this->config['api_key'] ?? '';

        return $base.'/models/'.urlencode($model).':generateContent?key='.urlencode($key);
    }

    protected function extractText(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }
        $candidates = $body['candidates'] ?? null;
        if (! is_array($candidates) || empty($candidates)) {
            return null;
        }
        $content = $candidates[0]['content'] ?? null;
        if (! is_array($content)) {
            return null;
        }
        $parts = $content['parts'] ?? null;
        if (! is_array($parts) || empty($parts)) {
            return null;
        }
        $text = $parts[0]['text'] ?? null;

        return is_string($text) ? trim($text) : null;
    }

    protected function parseJsonResponse(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/\s*```\s*$/', '', $text);
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{[\s\S]*\}/', $text, $m) === 1) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
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
            $cleaned = preg_replace('/[^0-9.-]/', '', $value);

            return $cleaned !== '' && is_numeric($cleaned) ? (float) $cleaned : null;
        }

        return null;
    }

    /**
     * Persist lookup result to mpn (current vendor price) and alt_vendors, and set inventory research_completed_at.
     * Call this after a successful lookup() for the given inventory.
     */
    public function persistLookupResult(Inventory $inventory, array $result): void
    {
        if (! ($result['success'] ?? false)) {
            return;
        }

        $now = now();
        $price = $result['current_vendor_price'] ?? null;
        $currency = $result['current_vendor_currency'] ?? 'USD';

        $inventory->mpns()->update([
            'unit_price' => $price,
            'price_fetched_at' => $now,
            'currency' => $currency,
        ]);

        $inventory->altVendors()->delete();
        foreach ($result['alt_vendors'] ?? [] as $alt) {
            AltVendor::create([
                'inventory_id' => $inventory->id,
                'vendor_name' => $alt['vendor_name'] ?? '',
                'unit_price' => $alt['unit_price'] ?? 0,
                'url' => $alt['url'] ?? null,
                'fetched_at' => $now,
            ]);
        }

        $inventory->update([
            'research_completed_at' => $now,
            'gemini_response_json' => $result,
        ]);
    }
}
