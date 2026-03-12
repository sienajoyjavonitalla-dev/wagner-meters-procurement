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
            'current_vendor_currency' => null,
            'alt_vendor_results' => [],
            'error' => null,
            'raw_text' => null,
        ];

        if (! $this->isEnabled()) {
            $emptyResult['error'] = 'Gemini disabled: missing GEMINI_API_KEY';

            return $emptyResult;
        }

        $cacheKey = $this->geminiCacheKey($vendorName, $productLine, $mpns, $quantity, 'full');
        $cached = ResearchedMpn::getCached($cacheKey, ResearchedMpn::SOURCE_GEMINI);
        if ($cached !== null && ! empty($cached['success'])) {
            if ($inventoryId !== null) {
                Log::info("item_id {$inventoryId}: Gemini AI (full) (researched_mpn)");
            }
            $cached['prompt'] = $cached['prompt'] ?? null;
            return $cached;
        }

        if ($inventoryId !== null) {
            Log::info("item_id {$inventoryId}: Gemini AI (full) (direct)");
        }

        $prompt = $this->buildPrompt($vendorName, $productLine, $mpns, $quantity);
        $emptyResult['prompt'] = $prompt;
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
            $err = is_array($json) && isset($json['error']['message'])
                ? $json['error']['message']
                : '';

            $isQuota = $response->status() === 429
                || (str_contains((string) $err, 'quota') && str_contains((string) $err, 'retry'));

            if ($isQuota && $attempt < $maxAttempts && preg_match('/retry in (\d+(?:\.\d+)?)\s*s/i', $err, $m)) {
                $wait = (int) ceil((float) $m[1]);
                $wait = min(max($wait, 5), 120);
                sleep($wait);
                continue;
            }

            $emptyResult['error'] = $err ?: 'Gemini API request failed (HTTP '.$response->status().')';

            return $emptyResult;
        }

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

        $currency = isset($parsed['current_vendor_currency']) && trim((string) $parsed['current_vendor_currency']) !== ''
            ? trim((string) $parsed['current_vendor_currency'])
            : 'USD';

        $currentVendorResults = [];
        $rawCurrent = $parsed['current_vendor_results'] ?? [];
        if (is_array($rawCurrent)) {
            foreach ($rawCurrent as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $partNumber = trim((string) ($row['part_number'] ?? ''));
                if ($partNumber === '') {
                    continue;
                }
                $price = $this->toFloat($row['unit_price'] ?? null);
                $url = isset($row['url']) && trim((string) $row['url']) !== ''
                    ? trim((string) $row['url'])
                    : null;
                $currentVendorResults[] = [
                    'part_number' => $partNumber,
                    'unit_price' => $price,
                    'url' => $url,
                ];
            }
        }

        $altVendorResults = [];
        $rawAltResults = $parsed['alt_vendor_results'] ?? [];
        if (is_array($rawAltResults)) {
            foreach ($rawAltResults as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $partNumber = trim((string) ($row['part_number'] ?? ''));
                if ($partNumber === '') {
                    continue;
                }
                $vendors = [];
                foreach ($row['vendors'] ?? [] as $alt) {
                    if (! is_array($alt)) {
                        continue;
                    }
                    $name = trim((string) ($alt['vendor_name'] ?? ''));
                    if (self::isDigiKeyOrMouser($name)) {
                        continue;
                    }
                    $price = $this->toFloat($alt['unit_price'] ?? null);
                    if ($name !== '' && $price !== null) {
                        $vendors[] = [
                            'vendor_name' => $name,
                            'unit_price' => $price,
                            'url' => isset($alt['url']) && trim((string) $alt['url']) !== '' ? trim((string) $alt['url']) : null,
                        ];
                    }
                }
                $altVendorResults[] = [
                    'part_number' => $partNumber,
                    'vendors' => $vendors,
                ];
            }
        }

        $result = [
            'success' => true,
            'current_vendor_results' => $currentVendorResults,
            'current_vendor_currency' => $currency,
            'alt_vendor_results' => $altVendorResults,
            'error' => null,
            'raw_text' => $text,
            'prompt' => $prompt,
        ];
        ResearchedMpn::setCached($cacheKey, ResearchedMpn::SOURCE_GEMINI, $result);
        return $result;
    }

    protected function buildPrompt(string $vendorName, string $productLine, array $mpns, float $quantity): string
    {
        $mpnList = array_filter(array_map('trim', $mpns));
        $mpnBlock = empty($mpnList)
            ? 'No part numbers provided.'
            : 'Manufacturing part numbers (search each one): '.implode(', ', $mpnList);

        return <<<PROMPT
You are a procurement research assistant. Answer with valid JSON only, no other text.

For each manufacturing part number (MPN) listed below, search for the unit price and product page URL from the current vendor ({$vendorName}). Then search for US-based alternative vendors that stock any of these parts and return their best price and link per vendor.

Context:
- Current vendor: {$vendorName}
- Product line: {$productLine}
- Quantity: {$quantity}
- {$mpnBlock}

Return a single JSON object with exactly these keys:
- "current_vendor_results" (array): one object per MPN, each with "part_number" (string, the MPN), "unit_price" (number or null), "url" (string or null). Search {$vendorName} for each part number and set price and product URL. Use the exact part_number strings listed above.
- "current_vendor_currency" (string): e.g. "USD"
- "alt_vendor_results" (array): one object per MPN, each with "part_number" (string, the MPN), "vendors" (array of objects). Each vendor object has "vendor_name" (string), "unit_price" (number), "url" (string or null). For each part number, list US-based alternative vendors that stock that part (e.g. Newark, Arrow, Avnet, Farnell). Do NOT include Digi-Key or Mouser in alt_vendor_results (those are filled from API separately). Use the exact part_number strings listed above. Sort each part's vendors by lowest unit_price first.

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

The current vendor ({$currentVendorName}) pricing is already known. For each manufacturing part number (MPN) below, search for US-based ALTERNATIVE vendors only (e.g. Newark, Arrow, Avnet, Farnell). Do NOT include {$currentVendorName} in the results. Do NOT include Digi-Key or Mouser in the results (those are filled from API separately). Return each part's alternative vendors with unit price and product URL, sorted by lowest unit_price first.

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
     * Build prompt for a single MPN (alt vendors only).
     */
    protected function buildPromptForAltVendorsOneMpn(string $currentVendorName, string $productLine, string $mpn, float $quantity): string
    {
        $mpn = trim($mpn);
        $mpnBlock = $mpn !== '' ? 'Manufacturing part number: '.$mpn : 'No part number provided.';

        return <<<PROMPT
You are a procurement research assistant. Answer with valid JSON only, no other text.

The current vendor ({$currentVendorName}) pricing is already known. For the manufacturing part number below, search for US-based ALTERNATIVE vendors only (e.g. Newark, Arrow, Avnet, Farnell). Do NOT include {$currentVendorName} in the results. Do NOT include Digi-Key or Mouser in the results (those are filled from API separately). Return alternative vendors with unit price and product URL, sorted by lowest unit_price first.

Context:
- Current vendor (exclude from results): {$currentVendorName}
- Product line: {$productLine}
- Quantity: {$quantity}
- {$mpnBlock}

Return a single JSON object with exactly this key:
- "alt_vendor_results" (array): one object with "part_number" (string, the exact MPN "{$mpn}"), "vendors" (array of objects). Each vendor object has "vendor_name" (string), "unit_price" (number), "url" (string or null). List only vendors that are NOT {$currentVendorName}. Do NOT include Digi-Key or Mouser. Sort vendors by lowest unit_price first.

Return only the JSON object.
PROMPT;
    }

    /**
     * Look up alternative vendors for a single MPN via Gemini. Uses cache_key = MPN in researched_mpn.
     *
     * @return array{success: bool, alt_vendor_results: array, error: string|null, raw_text: string|null, prompt: string|null}
     */
    protected function lookupAltVendorsForOneMpn(string $currentVendorName, string $productLine, string $mpn, float $quantity, ?int $inventoryId = null): array
    {
        $emptyResult = [
            'success' => false,
            'alt_vendor_results' => [],
            'error' => null,
            'raw_text' => null,
            'prompt' => null,
        ];

        $mpn = trim($mpn);
        if ($mpn === '') {
            $emptyResult['success'] = true;
            return $emptyResult;
        }

        $cached = ResearchedMpn::getCached($mpn, ResearchedMpn::SOURCE_GEMINI);
        if ($cached !== null && ! empty($cached['success']) && ! empty($cached['alt_vendor_results'])) {
            if ($inventoryId !== null) {
                Log::info("item_id {$inventoryId}: Gemini AI (alt) (researched_mpn)");
            }
            return $cached;
        }

        if ($inventoryId !== null) {
            Log::info("item_id {$inventoryId}: Gemini AI (alt) (direct)");
        }

        $prompt = $this->buildPromptForAltVendorsOneMpn($currentVendorName, $productLine, $mpn, $quantity);
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
                sleep($wait);
                continue;
            }
            $emptyResult['error'] = $err ?: 'Gemini API request failed (HTTP '.$response->status().')';
            return $emptyResult;
        }

        if ($response->failed()) {
            $emptyResult['error'] = is_array($response->json()) && isset($response->json()['error']['message'])
                ? $response->json()['error']['message']
                : 'Gemini API request failed (HTTP '.$response->status().')';
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

        $altVendorResults = [];
        $rawAltResults = $parsed['alt_vendor_results'] ?? [];
        if (is_array($rawAltResults)) {
            foreach ($rawAltResults as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $partNumber = trim((string) ($row['part_number'] ?? ''));
                if ($partNumber === '') {
                    $partNumber = $mpn;
                }
                $vendors = [];
                foreach ($row['vendors'] ?? [] as $alt) {
                    if (! is_array($alt)) {
                        continue;
                    }
                    $name = trim((string) ($alt['vendor_name'] ?? ''));
                    if (self::isDigiKeyOrMouser($name)) {
                        continue;
                    }
                    $price = $this->toFloat($alt['unit_price'] ?? null);
                    if ($name !== '' && $price !== null) {
                        $vendors[] = [
                            'vendor_name' => $name,
                            'unit_price' => $price,
                            'url' => isset($alt['url']) && trim((string) $alt['url']) !== '' ? trim((string) $alt['url']) : null,
                        ];
                    }
                }
                $altVendorResults[] = [
                    'part_number' => $partNumber,
                    'vendors' => $vendors,
                ];
            }
        }

        if ($altVendorResults === []) {
            $altVendorResults[] = ['part_number' => $mpn, 'vendors' => []];
        }

        $result = [
            'success' => true,
            'alt_vendor_results' => $altVendorResults,
            'error' => null,
            'raw_text' => $text,
            'prompt' => $prompt,
        ];
        ResearchedMpn::setCached($mpn, ResearchedMpn::SOURCE_GEMINI, $result);
        return $result;
    }

    /**
     * Look up alternative vendors only via Gemini (no current vendor lookup).
     * Calls Gemini once per MPN; cache_key in researched_mpn is the MPN so research can look up by cache_key/MPN.
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

        if (! $this->isEnabled()) {
            $emptyResult['error'] = 'Gemini disabled: missing GEMINI_API_KEY';
            return $emptyResult;
        }

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
            $one = $this->lookupAltVendorsForOneMpn($currentVendorName, $productLine, $partNumber, $quantity, $inventoryId);
            if (! empty($one['alt_vendor_results'])) {
                foreach ($one['alt_vendor_results'] as $row) {
                    $mergedAltVendorResults[] = $row;
                }
            }
            if (! empty($one['error'])) {
                $anyError = $one['error'];
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
