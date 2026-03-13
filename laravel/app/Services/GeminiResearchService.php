<?php

namespace App\Services;

use App\Models\AltVendor;
use App\Models\Inventory;
use App\Models\Mpn;
use App\Models\ResearchedMpn;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    public function lookup(string $vendorName, string $productLine, array $mpns, float $quantity, ?int $inventoryId = null): array
    {
        $emptyResult = [
            'success' => false,
            'current_vendor_results' => [],
            'current_vendor_currency' => 'USD',
            'alt_vendor_results' => [],
            'error' => null,
            'raw_text' => null,
        ];

        $mpns = array_filter(array_map('trim', $mpns));
        if ($mpns === []) {
            $emptyResult['error'] = 'No MPNs provided';
            return $emptyResult;
        }

        $currentVendorResults = [];
        $altVendorResults = [];
        $anyError = null;

        foreach ($mpns as $mpn) {
            $data = $this->lookupVendorsForOneMpn($mpn, $inventoryId);
            if (isset($data['error']) && $data['error'] !== null) {
                $anyError = $data['error'];
                continue;
            }
            $vendors = $data['vendors'] ?? [];
            if ($vendors === []) {
                continue;
            }
            $split = $this->fromVendorsListToPersistFormat($vendors, $vendorName, $quantity, $mpn);
            foreach ($split['current_vendor_results'] as $row) {
                $currentVendorResults[] = $row;
            }
            foreach ($split['alt_vendor_results'] as $row) {
                $altVendorResults[] = $row;
            }
        }

        if ($currentVendorResults === [] && $altVendorResults === [] && $anyError !== null) {
            $emptyResult['error'] = $anyError;
            Log::warning('Gemini lookup failed (no results for any MPN)', [
                'inventory_id' => $inventoryId,
                'vendor_name' => $vendorName,
                'mpns' => $mpns,
                'error' => $anyError,
            ]);
            return $emptyResult;
        }

        return [
            'success' => true,
            'current_vendor_results' => $currentVendorResults,
            'current_vendor_currency' => 'USD',
            'alt_vendor_results' => $altVendorResults,
            'error' => null,
            'raw_text' => null,
        ];
    }

    protected function buildPrompt(string $vendorName, string $productLine, array $mpns, float $quantity): string
    {
        $mpnList = array_filter(array_map('trim', $mpns));
        $mpnBlock = empty($mpnList)
            ? 'No part numbers provided.'
            : 'Manufacturing part numbers (search each one): '.implode(', ', $mpnList);

        return <<<PROMPT
You are a procurement research assistant. Answer with valid JSON only, no other text.

For each manufacturing part number (MPN) listed below, search for the unit price and product page URL from the current vendor ({$vendorName}). Then search for alternative vendors that stock any of these parts and return their best price and link per vendor. Only include vendors that are based in the United States and serve US customers directly. Do NOT include vendors primarily based outside the US (e.g. Farnell, RS Components, TME, element14 outside the US). Prefer US distributors such as Newark, Arrow, Avnet US. Do NOT include Digi-Key or Mouser in the alternative list (those are filled from API separately). If unsure whether a vendor is US-based, do not include it.

Context:
- Current vendor: {$vendorName}
- Product line: {$productLine}
- Quantity: {$quantity}
- {$mpnBlock}

Return a single JSON object with exactly these keys:
- "current_vendor_results" (array): one object per MPN, each with "part_number" (string, the MPN), "unit_price" (number or null), "url" (string or null). Search {$vendorName} for each part number and set price and product URL. Use the exact part_number strings listed above.
- "current_vendor_currency" (string): e.g. "USD"
- "alt_vendor_results" (array): one object per MPN, each with "part_number" (string, the MPN), "vendors" (array of objects). Each vendor object has "vendor_name" (string), "unit_price" (number), "url" (string or null). For each part number, list alternative vendors that stock that part. Only include vendors based in the United States (e.g. Newark, Arrow, Avnet US). Do NOT include Digi-Key or Mouser in alt_vendor_results (those are filled from API separately). Do NOT include non-US vendors (e.g. Farnell, RS Components, TME). Use the exact part_number strings listed above. Sort each part's vendors by lowest unit_price first.

Return only the JSON object.
PROMPT;
    }

    /**
     * Build a prompt that asks only for alternative vendors (not current vendor).
     * Used when current vendor pricing was already fetched via DigiKey/Mouser API.
     */
    protected function buildPromptForAltVendorsOnly(string $currentVendorName, string $productLine, array $mpns, float $quantity): string
    {
        $mpnList = array_filter(array_map('trim', $mpns));
        $mpnBlock = empty($mpnList)
            ? 'No part numbers provided.'
            : 'Manufacturing part numbers (search each one): '.implode(', ', $mpnList);

        return <<<PROMPT
You are a procurement research assistant. Answer with valid JSON only, no other text.

The current vendor ({$currentVendorName}) pricing is already known. For each manufacturing part number (MPN) below, search for ALTERNATIVE vendors only. Only include vendors that are based in the United States and serve US customers directly (e.g. Newark, Arrow, Avnet US). Do NOT include {$currentVendorName} in the results. Do NOT include Digi-Key or Mouser in the results (those are filled from API separately). Do NOT include vendors primarily based outside the US (e.g. Farnell, RS Components, TME). If unsure whether a vendor is US-based, do not include it. Return each part's alternative vendors with unit price and product URL, sorted by lowest unit_price first.

Context:
- Current vendor (exclude from results): {$currentVendorName}
- Product line: {$productLine}
- Quantity: {$quantity}
- {$mpnBlock}

Return a single JSON object with exactly this key:
- "alt_vendor_results" (array): one object per MPN, each with "part_number" (string, the exact MPN), "vendors" (array of objects). Each vendor object has "vendor_name" (string), "unit_price" (number), "url" (string or null). List only vendors that are NOT {$currentVendorName}. Do NOT include Digi-Key or Mouser (those are filled from API separately). Use the exact part_number strings listed above. Sort each part's vendors by lowest unit_price first.

Return only the JSON object.
PROMPT;
    }

    /**
     * Build prompt for a single MPN: all vendors (current + alternative) with price tiers.
     * Stored in researched_mpn with cache_key = MPN so any quantity/vendor can reuse.
     */
    protected function buildPromptVendorsOneMpn(string $mpn): string
    {
        $mpn = trim($mpn);
        $mpnBlock = $mpn !== '' ? 'Manufacturing part number: '.$mpn : 'No part number provided.';

        return <<<PROMPT
You are a procurement research assistant. Answer with valid JSON only, no other text.

For the single part number below, find all distributors that stock it and are based in the United States (e.g. Digi-Key, Mouser, Newark, Arrow, Avnet US). Only include vendors that are US-based and serve US customers directly. Do NOT include vendors primarily based outside the US (e.g. Farnell, RS Components, TME). For each vendor return: vendor_name, product URL, and price at several quantity tiers. Use the exact decimal precision shown on each vendor's website (e.g. 3 or 4 decimal places).

{$mpnBlock}

Return a single JSON object with exactly this key:
- "vendors" (array): one object per vendor. Each object has:
  - "vendor_name" (string)
  - "url" (string or null)
  - "price_breaks" (array): list of { "quantity" (number), "unit_price" (number) } for different order quantities. Include tiers that the vendor shows (e.g. 1, 10, 100, 500, 1000, 5000, 10000). Use the exact unit_price decimals from the vendor site.

Return only the JSON object.
PROMPT;
    }

    /**
     * Get unit price for a given quantity from Gemini-style price_breaks (quantity, unit_price).
     * Uses the tier where break quantity <= requested quantity; if below all tiers, uses smallest tier price.
     */
    protected function priceForQuantityFromBreaks(array $priceBreaks, float $quantity): ?float
    {
        if ($priceBreaks === []) {
            return null;
        }
        $q = (int) ceil($quantity);
        $withQty = [];
        foreach ($priceBreaks as $b) {
            $bq = (int) ($b['quantity'] ?? 0);
            $up = $b['unit_price'] ?? null;
            if ($up !== null && $up !== '') {
                $withQty[] = ['qty' => $bq, 'price' => (float) $up];
            }
        }
        if ($withQty === []) {
            return null;
        }
        usort($withQty, fn ($a, $b) => $b['qty'] <=> $a['qty']);
        foreach ($withQty as $tier) {
            if ($tier['qty'] <= $q) {
                return (float) $tier['price'];
            }
        }
        $minTier = $withQty[array_key_last($withQty)] ?? null;

        return $minTier ? (float) $minTier['price'] : null;
    }

    /**
     * Convert unified "vendors" list (with price_breaks) to persist format for one MPN.
     * Picks unit_price from price_breaks for the given quantity; splits by currentVendorName into current vs alt.
     *
     * @return array{current_vendor_results: array, alt_vendor_results: array}
     */
    protected function fromVendorsListToPersistFormat(array $vendors, string $currentVendorName, float $quantity, string $mpn): array
    {
        $currentNorm = strtolower(trim($currentVendorName));
        $currentNormNoSpaces = str_replace(' ', '', $currentNorm);
        $currentVendorResults = [];
        $altVendors = [];

        foreach ($vendors as $v) {
            if (! is_array($v)) {
                continue;
            }
            $name = trim((string) ($v['vendor_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $url = isset($v['url']) && trim((string) $v['url']) !== '' ? trim((string) $v['url']) : null;
            $priceBreaks = $v['price_breaks'] ?? [];
            $unitPrice = $this->priceForQuantityFromBreaks($priceBreaks, $quantity);
            if ($unitPrice === null && ! empty($priceBreaks)) {
                $first = reset($priceBreaks);
                $unitPrice = isset($first['unit_price']) ? (float) $first['unit_price'] : null;
            }
            $entry = [
                'vendor_name' => $name,
                'unit_price' => $unitPrice,
                'url' => $url,
            ];
            $nameNorm = strtolower($name);
            $nameNormNoSpaces = str_replace(' ', '', $nameNorm);
            $isCurrent = ($nameNorm === $currentNorm || $nameNormNoSpaces === $currentNormNoSpaces
                || str_contains($nameNormNoSpaces, $currentNormNoSpaces) || str_contains($currentNormNoSpaces, $nameNormNoSpaces));
            if ($isCurrent && $unitPrice !== null) {
                $currentVendorResults[] = [
                    'part_number' => $mpn,
                    'unit_price' => $unitPrice,
                    'url' => $url,
                ];
            } else {
                if ($unitPrice !== null) {
                    $altVendors[] = $entry;
                }
            }
        }

        $altVendorResults = [];
        if ($altVendors !== []) {
            $altVendorResults[] = ['part_number' => $mpn, 'vendors' => $altVendors];
        }

        return [
            'current_vendor_results' => $currentVendorResults,
            'alt_vendor_results' => $altVendorResults,
        ];
    }

    /**
     * Look up all vendors (current + alternative) for one MPN via Gemini. Cache by MPN only.
     * Stored response_json is solely { "vendors": [...] } so any quantity/vendor can reuse.
     *
     * @return array{vendors: array, error: string|null}
     */
    public function lookupVendorsForOneMpn(string $mpn, ?int $inventoryId = null): array
    {
        $mpn = trim($mpn);
        $empty = ['vendors' => [], 'error' => null];
        if ($mpn === '') {
            return $empty;
        }

        $cached = ResearchedMpn::getCached($mpn, ResearchedMpn::SOURCE_GEMINI);
        if ($cached !== null && isset($cached['vendors']) && is_array($cached['vendors'])) {
            $cachedVendors = $cached['vendors'];
            if ($inventoryId !== null) {
                Log::info('Gemini API: cache hit (researched_mpn)', [
                    'mpn' => $mpn,
                    'inventory_id' => $inventoryId,
                    'vendors_count' => count($cachedVendors),
                    'vendor_names' => array_column($cachedVendors, 'vendor_name'),
                ]);
            }
            return ['vendors' => $cachedVendors, 'error' => null];
        }

        if (! $this->isEnabled()) {
            $empty['error'] = 'Gemini disabled: missing GEMINI_API_KEY';
            return $empty;
        }

        if ($inventoryId !== null) {
            Log::info("item_id {$inventoryId}: Gemini AI (direct) calling API for MPN", ['mpn' => $mpn]);
        }

        $prompt = $this->buildPromptVendorsOneMpn($mpn);
        $url = $this->buildUrl();
        $payload = $this->buildGenerationPayload($prompt);

        $maxAttempts = 3;
        $attempt = 0;
        $response = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $response = Http::timeout(60)->post($url, $payload);
            if (! $response->failed()) {
                break;
            }
            $json = $response->json();
            $err = is_array($json) && isset($json['error']['message']) ? $json['error']['message'] : '';
            $isQuota = $response->status() === 429 || (str_contains((string) $err, 'quota') && str_contains((string) $err, 'retry'));
            if ($isQuota && $attempt < $maxAttempts && preg_match('/retry in (\d+(?:\.\d+)?)\s*s/i', $err, $m)) {
                $wait = min(max((int) ceil((float) $m[1]), 5), 120);
                Log::warning('Gemini API: quota/rate limit, retrying', [
                    'mpn' => $mpn,
                    'inventory_id' => $inventoryId,
                    'attempt' => $attempt,
                    'wait_seconds' => $wait,
                    'error' => $err,
                ]);
                sleep($wait);
                continue;
            }
            $empty['error'] = $err ?: 'Gemini API request failed (HTTP '.$response->status().')';
            Log::warning('Gemini API call failed', [
                'mpn' => $mpn,
                'inventory_id' => $inventoryId,
                'attempt' => $attempt,
                'http_status' => $response->status(),
                'error' => $empty['error'],
            ]);
            return $empty;
        }

        if ($response->failed()) {
            $errMsg = is_array($response->json()) && isset($response->json()['error']['message'])
                ? $response->json()['error']['message']
                : 'Gemini API request failed (HTTP '.$response->status().')';
            $empty['error'] = $errMsg;
            Log::warning('Gemini API call failed (after retries)', [
                'mpn' => $mpn,
                'inventory_id' => $inventoryId,
                'http_status' => $response->status(),
                'error' => $errMsg,
            ]);
            return $empty;
        }

        $body = $response->json();
        $text = $this->extractText($body);
        if ($text === null || $text === '') {
            $empty['error'] = 'Gemini response did not include any text content';
            Log::warning('Gemini API: response had no text content', [
                'mpn' => $mpn,
                'inventory_id' => $inventoryId,
                'error' => $empty['error'],
            ]);
            return $empty;
        }

        $parsed = $this->parseJsonResponse($text);
        if (! is_array($parsed) || ! isset($parsed['vendors']) || ! is_array($parsed['vendors'])) {
            $parseError = 'Gemini response was not valid JSON or missing "vendors"';
            Log::warning('Gemini API: invalid or missing vendors in response', [
                'mpn' => $mpn,
                'inventory_id' => $inventoryId,
                'error' => $parseError,
                'raw_text_preview' => is_string($text) ? mb_substr($text, 0, 300) : '(non-string)',
            ]);
            return ['vendors' => [], 'error' => $parseError, 'raw_text' => $text];
        }

        $vendors = [];
        foreach ($parsed['vendors'] as $v) {
            if (! is_array($v)) {
                continue;
            }
            $name = trim((string) ($v['vendor_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $priceBreaks = $v['price_breaks'] ?? [];
            if (! is_array($priceBreaks)) {
                $priceBreaks = [];
            }
            $vendors[] = [
                'vendor_name' => $name,
                'url' => isset($v['url']) && trim((string) $v['url']) !== '' ? trim((string) $v['url']) : null,
                'price_breaks' => $priceBreaks,
            ];
        }

        $toStore = ['vendors' => $vendors];
        ResearchedMpn::setCached($mpn, ResearchedMpn::SOURCE_GEMINI, $toStore, null);
        Log::info('Gemini API call succeeded', [
            'mpn' => $mpn,
            'inventory_id' => $inventoryId,
            'vendors_count' => count($vendors),
            'vendor_names' => array_column($vendors, 'vendor_name'),
        ]);
        return ['vendors' => $vendors, 'error' => null];
    }

    /**
     * Build prompt for a single MPN (alt vendors only). Kept for reference; primary flow uses buildPromptVendorsOneMpn.
     */
    protected function buildPromptForAltVendorsOneMpn(string $currentVendorName, string $productLine, string $mpn, float $quantity): string
    {
        $mpn = trim($mpn);
        $mpnBlock = $mpn !== '' ? 'Manufacturing part number: '.$mpn : 'No part number provided.';

        return <<<PROMPT
You are a procurement research assistant. Answer with valid JSON only, no other text.

The current vendor ({$currentVendorName}) pricing is already known. For the manufacturing part number below, search for ALTERNATIVE vendors only. Only include vendors that are based in the United States and serve US customers directly (e.g. Newark, Arrow, Avnet US). Do NOT include {$currentVendorName} in the results. Do NOT include Digi-Key or Mouser in the results (those are filled from API separately). Do NOT include vendors primarily based outside the US (e.g. Farnell, RS Components, TME). If unsure whether a vendor is US-based, do not include it. Return alternative vendors with unit price and product URL, sorted by lowest unit_price first.

Context:
- Current vendor (exclude from results): {$currentVendorName}
- Product line: {$productLine}
- Quantity: {$quantity}
- {$mpnBlock}

Return a single JSON object with exactly this key:
- "alt_vendor_results" (array): one object with "part_number" (string, the exact MPN "{$mpn}"), "vendors" (array of objects). Each vendor object has "vendor_name" (string), "unit_price" (number), "url" (string or null). List only US-based vendors that are NOT {$currentVendorName}. Do NOT include Digi-Key or Mouser. Do NOT include non-US vendors (e.g. Farnell, RS Components, TME). Sort vendors by lowest unit_price first.

Return only the JSON object.
PROMPT;
    }

    /**
     * Build alt_vendor_results from unified vendors list for one MPN: exclude DigiKey/Mouser, pick price at quantity from price_breaks.
     */
    protected function vendorsListToAltVendorResults(array $vendors, float $quantity, string $mpn): array
    {
        $altVendors = [];
        foreach ($vendors as $v) {
            if (! is_array($v)) {
                continue;
            }
            $name = trim((string) ($v['vendor_name'] ?? ''));
            if ($name === '' || self::isDigiKeyOrMouser($name)) {
                continue;
            }
            $priceBreaks = $v['price_breaks'] ?? [];
            $unitPrice = $this->priceForQuantityFromBreaks($priceBreaks, $quantity);
            if ($unitPrice === null && ! empty($priceBreaks)) {
                $first = reset($priceBreaks);
                $unitPrice = isset($first['unit_price']) ? (float) $first['unit_price'] : null;
            }
            if ($unitPrice === null) {
                continue;
            }
            $altVendors[] = [
                'vendor_name' => $name,
                'unit_price' => $unitPrice,
                'url' => isset($v['url']) && trim((string) $v['url']) !== '' ? trim((string) $v['url']) : null,
            ];
        }
        if ($altVendors === []) {
            return [];
        }
        return [['part_number' => $mpn, 'vendors' => $altVendors]];
    }

    /**
     * Look up alternative vendors only via Gemini (no current vendor lookup).
     * Uses same per-MPN cache as full lookup (lookupVendorsForOneMpn); excludes DigiKey/Mouser from results; price from price_breaks at quantity.
     *
     * @param  array<int, string>  $mpns
     * @return array{success: bool, alt_vendor_results: array, error: string|null, raw_text: string|null, prompt: string|null}
     */
    public function lookupAltVendorsOnly(string $currentVendorName, string $productLine, array $mpns, float $quantity, ?int $inventoryId = null): array
    {
        $emptyResult = [
            'success' => false,
            'alt_vendor_results' => [],
            'error' => null,
            'raw_text' => null,
            'prompt' => null,
        ];

        $mpns = array_filter(array_map('trim', $mpns));
        if ($mpns === []) {
            $emptyResult['success'] = true;
            return $emptyResult;
        }

        $mergedAltVendorResults = [];
        $anyError = null;
        foreach ($mpns as $partNumber) {
            $partNumber = trim((string) $partNumber);
            if ($partNumber === '') {
                continue;
            }
            $data = $this->lookupVendorsForOneMpn($partNumber, $inventoryId);
            if (isset($data['error']) && $data['error'] !== null) {
                $anyError = $data['error'];
                continue;
            }
            $vendors = $data['vendors'] ?? [];
            foreach ($this->vendorsListToAltVendorResults($vendors, $quantity, $partNumber) as $row) {
                $mergedAltVendorResults[] = $row;
            }
        }

        return [
            'success' => $anyError === null,
            'alt_vendor_results' => $mergedAltVendorResults,
            'error' => $anyError,
            'raw_text' => null,
            'prompt' => null,
        ];
    }

    protected function buildUrl(): string
    {
        $base = rtrim($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta', '/');
        $model = $this->config['model'] ?? 'gemini-2.5-flash-lite';
        $key = $this->config['api_key'] ?? '';

        return $base.'/models/'.urlencode($model).':generateContent?key='.urlencode($key);
    }

    /** @return array{contents: array, generationConfig?: array, generation_config?: array} */
    protected function buildGenerationPayload(string $prompt): array
    {
        $base = $this->config['base_url'] ?? '';
        $maxTokens = $this->config['max_output_tokens'] ?? 2048;
        $isV1Beta = str_contains($base, 'v1beta');

        $contents = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
        ];

        if ($isV1Beta) {
            $contents['generationConfig'] = [
                'temperature' => 0.2,
                'maxOutputTokens' => $maxTokens,
                'responseMimeType' => 'application/json',
            ];
        } else {
            $contents['generation_config'] = [
                'temperature' => 0.2,
                'max_output_tokens' => $maxTokens,
            ];
        }

        return $contents;
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
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $m) === 1) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $this->parseTruncatedJson($text);
    }

    /**
     * Attempt to parse JSON that may be truncated (e.g. long URL cut off inside alt_vendors).
     */
    protected function parseTruncatedJson(string $text): ?array
    {
        $repaired = $text;
        if (! str_ends_with(preg_replace('/\s+/', '', $repaired), '}')) {
            $repaired = rtrim($repaired);
            if (preg_match('/"url"\s*:\s*"[^"]*$/s', $repaired)) {
                $repaired .= '"';
            }
            $repaired .= str_repeat(']', max(0, substr_count($repaired, '[') - substr_count($repaired, ']')));
            $repaired .= str_repeat('}', max(0, substr_count($repaired, '{') - substr_count($repaired, '}')));
        }
        $decoded = json_decode($repaired, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function geminiCacheKey(string $vendorName, string $productLine, array $mpns, float $quantity, string $type): string
    {
        $sorted = $mpns;
        sort($sorted);
        $parts = [
            trim($vendorName),
            trim($productLine),
            implode(',', array_map('trim', $sorted)),
            (string) $quantity,
            $type,
        ];
        return 'gemini:' . md5(implode('|', $parts));
    }

    /**
     * Whether the vendor name is DigiKey or Mouser (alt vendor data for these comes from API only, not Gemini).
     */
    protected static function isDigiKeyOrMouser(string $vendorName): bool
    {
        $n = strtolower(trim($vendorName));
        $nNoSpaces = str_replace(' ', '', $n);
        return str_contains($nNoSpaces, 'digikey') || str_contains($n, 'digi-key') || str_contains($n, 'digi key')
            || str_contains($n, 'mouser');
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
     * Persist lookup result: update each MPN by part_number (price, url), alt_vendors, and set research_completed_at.
     * Call from PersistGeminiResultJob after a successful lookup().
     */
    public function persistLookupResult(Inventory $inventory, array $result): void
    {
        if (! ($result['success'] ?? false)) {
            return;
        }

        $now = now();
        $currency = $result['current_vendor_currency'] ?? 'USD';

        // Clear current-vendor price/url on all MPNs so only the ones in this result are set (avoids stale data from other MPNs or previous runs).
        $inventory->mpns()->update([
            'unit_price' => null,
            'url' => null,
            'price_fetched_at' => null,
            'currency' => null,
        ]);

        foreach ($result['current_vendor_results'] ?? [] as $row) {
            $partNumber = trim((string) ($row['part_number'] ?? ''));
            if ($partNumber === '') {
                continue;
            }
            $mpn = $inventory->mpns()->where('part_number', $partNumber)->first();
            if ($mpn) {
                $mpn->update([
                    'unit_price' => $row['unit_price'] ?? null,
                    'url' => $row['url'] ?? null,
                    'price_fetched_at' => $now,
                    'currency' => $currency,
                ]);
            }
        }

        foreach ($inventory->mpns as $mpn) {
            $mpn->altVendors()->delete();
        }
        foreach ($result['alt_vendor_results'] ?? [] as $row) {
            $partNumber = trim((string) ($row['part_number'] ?? ''));
            if ($partNumber === '') {
                continue;
            }
            $mpn = $inventory->mpns()->where('part_number', $partNumber)->first();
            if (! $mpn) {
                continue;
            }
            foreach ($row['vendors'] ?? [] as $alt) {
                AltVendor::create([
                    'mpn_id' => $mpn->id,
                    'vendor_name' => $alt['vendor_name'] ?? '',
                    'unit_price' => $alt['unit_price'] ?? 0,
                    'url' => $alt['url'] ?? null,
                    'fetched_at' => $now,
                ]);
            }
        }

        $stored = $result;
        unset($stored['prompt']);
        $inventory->update([
            'research_completed_at' => $now,
            'gemini_response_json' => $stored,
        ]);
    }
}
