<?php

namespace App\DTO;

/**
 * Normalized price-finding shape returned by provider clients (DigiKey, Mouser, Nexar).
 * Maps to price_findings table: provider, currency, price_breaks_json, min_unit_price.
 */
final readonly class PriceFindingData
{
    public function __construct(
        public string $provider,
        public ?string $currency,
        /** @var array<int, array{qty: int, price: float}> */
        public array $priceBreaks,
        public ?float $minUnitPrice,
    ) {
    }

    /**
     * @return array{provider: string, currency: ?string, price_breaks_json: array, min_unit_price: ?float}
     */
    public function toPriceFindingAttributes(): array
    {
        $breaks = array_values(array_map(
            fn (array $b) => ['qty' => (int) $b['qty'], 'price' => (float) $b['price']],
            $this->priceBreaks
        ));

        return [
            'provider' => $this->provider,
            'currency' => $this->currency,
            'price_breaks_json' => $breaks,
            'min_unit_price' => $this->minUnitPrice,
        ];
    }
}
