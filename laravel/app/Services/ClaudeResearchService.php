<?php

namespace App\Services;

use App\DTO\PriceFindingData;
use Illuminate\Support\Facades\Http;

class ClaudeResearchService
{
    public function __construct(
        protected array $config
    ) {
        $this->config = array_merge(config('procurement.claude', []), $config);
    }

    public static function fromConfig(): self
    {
        return new self(config('procurement.claude', []));
    }

    public function isEnabled(): bool
    {
        return ! empty($this->config['api_key']);
    }

    /**
     * Call Claude to research distributor pricing for the given task context.
     * Returns one normalized PriceFindingData (provider=claude) or null on failure/missing key.
     *
     * @return array<int, PriceFindingData>
     */
    public function lookup(string $vendor, string $itemId, string $description, string $queryMpn): array
    {
        $result = $this->debugLookup($vendor, $itemId, $description, $queryMpn);
        return $result['findings'];
    }

    /**
     * Same as lookup(), but includes debug info to surface API/parsing failures.
     *
     * @return array{
     *   findings: array<int, PriceFindingData>,
     *   http_status: int|null,
     *   error: string|null,
     *   raw_text: string|null,
     *   response_json: array|null
     * }
     */
    public function debugLookup(string $vendor, string $itemId, string $description, string $queryMpn): array
    {
        if (! $this->isEnabled()) {
            return [
                'findings' => [],
                'http_status' => null,
                'error' => 'Claude disabled: missing ANTHROPIC_API_KEY',
                'raw_text' => null,
                'response_json' => null,
            ];
        }

        $prompt = $this->buildPrompt($vendor, $itemId, $description, $queryMpn);

        $url = rtrim($this->config['base_url'] ?? '', '/') . '/messages';

        $response = Http::withHeaders($this->headers())
            ->timeout(60)
            ->post($url, [
                'model' => $this->config['model'] ?? 'claude-sonnet-4-20250514',
                'max_tokens' => $this->config['max_tokens'] ?? 1024,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        if ($response->failed()) {
            $err = null;
            $json = $response->json();
            if (is_array($json) && isset($json['error'])) {
                $err = is_string($json['error']['message'] ?? null) ? $json['error']['message'] : json_encode($json['error']);
            }

            return [
                'findings' => [],
                'http_status' => $response->status(),
                'error' => $err ?? ('Claude API request failed (HTTP ' . $response->status() . ')'),
                'raw_text' => null,
                'response_json' => is_array($json) ? $json : null,
            ];
        }

        $body = $response->json();
        $text = $this->extractText($body);
        if ($text === null || $text === '') {
            return [
                'findings' => [],
                'http_status' => $response->status(),
                'error' => 'Claude response did not include any text content',
                'raw_text' => null,
                'response_json' => is_array($body) ? $body : null,
            ];
        }

        $parsed = $this->parseJsonFromResponse($text);
        if (! is_array($parsed)) {
            return [
                'findings' => [],
                'http_status' => $response->status(),
                'error' => 'Claude response was not valid JSON (or contained extra non-JSON text)',
                'raw_text' => $text,
                'response_json' => is_array($body) ? $body : null,
            ];
        }

        $finding = $this->mapToPriceFindingData($parsed);
        return [
            'findings' => $finding !== null ? [$finding] : [],
            'http_status' => $response->status(),
            'error' => $finding !== null ? null : 'Claude JSON did not contain a usable low_unit_price',
            'raw_text' => $text,
            'response_json' => is_array($body) ? $body : null,
        ];
    }

    protected function buildPrompt(string $vendor, string $itemId, string $description, string $queryMpn): string
    {
        return <<<PROMPT
Research distributor pricing and alternates for this item and return only valid JSON, no other text.

Context:
- vendor: {$vendor}
- internal item_id: {$itemId}
- description: {$description}
- candidate part number: {$queryMpn}

Required JSON shape (use exactly these keys):
{
  "matched_part": "string",
  "manufacturer": "string",
  "low_unit_price": 0.0,
  "currency": "USD",
  "source_url": "https://...",
  "note": "short text"
}

Return only the JSON object.
PROMPT;
    }

    protected function headers(): array
    {
        return [
            'x-api-key' => $this->config['api_key'] ?? '',
            'anthropic-version' => $this->config['anthropic_version'] ?? '2023-06-01',
            'content-type' => 'application/json',
        ];
    }

    protected function extractText(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $content = $body['content'] ?? null;
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return null;
        }

        $chunks = [];
        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }
            // Typical Messages API shape: { type: "text", text: "..." }
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $chunks[] = $block['text'];
                continue;
            }
            // Fallback: any block containing 'text'
            if (is_string($block['text'] ?? null)) {
                $chunks[] = $block['text'];
            }
        }

        $joined = trim(implode("\n", $chunks));
        return $joined !== '' ? $joined : null;
    }

    protected function parseJsonFromResponse(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/\s*```\s*$/', '', $text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // If Claude included extra text, try to extract the first JSON object.
        if (preg_match('/\{[\s\S]*\}/', $text, $m) === 1) {
            $decoded = json_decode($m[0], true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    protected function mapToPriceFindingData(array $parsed): ?PriceFindingData
    {
        $price = $this->toFloat($parsed['low_unit_price'] ?? null);
        $currency = isset($parsed['currency']) && (string) $parsed['currency'] !== ''
            ? (string) $parsed['currency']
            : null;

        $priceBreaks = [];
        if ($price !== null) {
            $priceBreaks[] = ['qty' => 1, 'price' => $price];
        }
        $matchedMpn = isset($parsed['matched_part']) && trim((string) $parsed['matched_part']) !== ''
            ? trim((string) $parsed['matched_part'])
            : null;

        return new PriceFindingData(
            provider: 'claude',
            currency: $currency,
            priceBreaks: $priceBreaks,
            minUnitPrice: $price,
            matchedMpn: $matchedMpn
        );
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
