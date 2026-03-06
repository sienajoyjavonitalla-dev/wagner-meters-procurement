<?php

namespace App\Services;

use App\DTO\PriceFindingData;
use Illuminate\Support\Facades\Http;

class NexarClient
{
    protected ?string $token = null;

    public function __construct(
        protected array $config
    ) {
        $this->config = array_merge(config('procurement.nexar', []), $config);
    }

    public static function fromConfig(): self
    {
        return new self(config('procurement.nexar', []));
    }

    public function isEnabled(): bool
    {
        return ! empty($this->config['client_id']) && ! empty($this->config['client_secret']);
    }

    /**
     * GraphQL supSearchMpn. Returns up to 3 normalized findings.
     *
     * @return array<int, PriceFindingData>
     */
    public function lookup(string $queryMpn): array
    {
        $token = $this->getToken();
        if (! $token) {
            return [];
        }

        $query = <<<'GQL'
query ($q: String!) {
  supSearchMpn(q: $q, limit: 3) {
    results {
      part { mpn shortDescription manufacturer { name } }
      sellers { company { name } offers { prices { quantity price currency } } }
    }
  }
}
GQL;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])
            ->timeout(20)
            ->post($this->config['graphql_url'] ?? '', [
                'query' => $query,
                'variables' => ['q' => $queryMpn],
            ]);

        if ($response->failed()) {
            return [];
        }

        $data = $response->json();
        $results = $data['data']['supSearchMpn']['results'] ?? [];
        if (! is_array($results)) {
            return [];
        }

        $findings = [];
        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $part = $row['part'] ?? [];
            $bestPrice = null;
            $currency = null;
            $priceBreaks = [];

            foreach ($row['sellers'] ?? [] as $seller) {
                if (! is_array($seller)) {
                    continue;
                }
                foreach ($seller['offers'] ?? [] as $offer) {
                    if (! is_array($offer)) {
                        continue;
                    }
                    foreach ($offer['prices'] ?? [] as $p) {
                        if (! is_array($p)) {
                            continue;
                        }
                        $price = $this->toFloat($p['price'] ?? null);
                        $qty = (int) ($p['quantity'] ?? 0);
                        if ($price !== null) {
                            $priceBreaks[] = ['qty' => $qty, 'price' => $price];
                            if ($bestPrice === null || $price < $bestPrice) {
                                $bestPrice = $price;
                                $currency = (string) ($p['currency'] ?? '');
                            }
                        }
                    }
                }
            }

            $matchedMpn = trim((string) ($part['mpn'] ?? '')) ?: null;
            $findings[] = new PriceFindingData(
                provider: 'nexar',
                currency: $currency ?: null,
                priceBreaks: $priceBreaks,
                minUnitPrice: $bestPrice,
                matchedMpn: $matchedMpn
            );
        }

        return $findings;
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
                'grant_type' => 'client_credentials',
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
            ]);

        if ($response->failed()) {
            return null;
        }

        $this->token = $response->json('access_token');
        return $this->token;
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
