<?php

namespace App\Services;

use App\DTO\PriceFindingData;
use App\Models\Inventory;

/**
 * Tries to get current-vendor pricing from DigiKey or Mouser API when the inventory vendor matches.
 * Returns a result shape compatible with persistLookupResult.
 * For alt_vendors: calls DigiKey and/or Mouser API depending on current vendor (skips the current vendor's API).
 * Price is chosen from API price breaks based on the quantity.
 */
class VendorApiResearchService
{
    public function __construct(
        protected DigiKeyClient $digiKey,
        protected MouserClient $mouser,
        protected Element14Client $element14,
    ) {
    }

    /**
     * If the inventory's vendor is DigiKey or Mouser and the provider is enabled, look up each MPN via API
     * and return a result array suitable for PersistGeminiResultJob. Otherwise return null (caller should use Gemini).
     *
     * @return array{success: true, current_vendor_results: array, current_vendor_currency: string, alt_vendor_results: array, source: string}|null
     */
    public function tryLookup(Inventory $inventory): ?array
    {
        $vendorName = (string) ($inventory->vendor_name ?? '');
        $normalized = strtolower(trim($vendorName));
        $normalizedNoSpaces = str_replace(' ', '', $normalized);
        $quantity = (float) ($inventory->quantity ?? 1);
        if ($quantity < 1) {
            $quantity = 1;
        }

        $mpns = $inventory->mpns->sortBy('id')->values()->pluck('part_number')->filter()->values()->all();
        if ($mpns === []) {
            return null;
        }

        if (str_contains($normalizedNoSpaces, 'digikey') || str_contains($normalized, 'digi-key') || str_contains($normalized, 'digi key')) {
            return $this->lookupDigiKey($mpns, $quantity, $inventory->id);
        }
        if (str_contains($normalized, 'mouser')) {
            return $this->lookupMouser($mpns, $quantity, $inventory->id);
        }
        if ($this->isElement14Vendor($normalized)) {
            return $this->lookupElement14($mpns, $quantity, $inventory->id);
        }

        return null;
    }

    /**
     * Detect whether vendor name refers to element14/Newark/Farnell.
     */
    protected function isElement14Vendor(string $normalizedVendorName): bool
    {
        $n = $normalizedVendorName;
        $nNoSpaces = str_replace(' ', '', $n);
        return str_contains($nNoSpaces, 'element14')
            || str_contains($n, 'farnell')
            || str_contains($n, 'newark');
    }

    /**
     * Get unit price for a given quantity from price breaks (tiered pricing).
     * Uses the tier where break_qty <= quantity with the largest break_qty; if quantity is below all tiers, uses the smallest tier price.
     *
     * @param  array<int, array{qty: int, price: float}>  $priceBreaks
     */
    public static function priceForQuantity(array $priceBreaks, float $quantity): ?float
    {
        if ($priceBreaks === []) {
            return null;
        }
        $q = (int) ceil($quantity);
        usort($priceBreaks, fn ($a, $b) => ($b['qty'] ?? 0) <=> ($a['qty'] ?? 0));
        foreach ($priceBreaks as $break) {
            $bqty = (int) ($break['qty'] ?? 0);
            if ($bqty <= $q) {
                return isset($break['price']) ? (float) $break['price'] : null;
            }
        }
        $minTier = $priceBreaks[array_key_last($priceBreaks)] ?? null;

        return $minTier && isset($minTier['price']) ? (float) $minTier['price'] : null;
    }

    /**
     * @param  array<int, string>  $mpns
     * @return array{success: true, current_vendor_results: array, current_vendor_currency: string, alt_vendor_results: array, source: string}|null
     */
    protected function lookupDigiKey(array $mpns, float $quantity, int $inventoryId): ?array
    {
        if (! $this->digiKey->isEnabled()) {
            return null;
        }

        $currentVendorResults = [];
        $currency = 'USD';

        foreach ($mpns as $index => $partNumber) {
            $partNumber = trim((string) $partNumber);
            if ($partNumber === '') {
                continue;
            }
            $findings = $this->digiKey->lookup($partNumber, null, $inventoryId);
            $finding = $this->bestMatchingFinding($findings, $partNumber);

            if ($finding === null) {
                continue;
            }
            $unitPrice = self::priceForQuantity($finding->priceBreaks, $quantity);
            if ($unitPrice === null) {
                $unitPrice = $finding->minUnitPrice;
            }
            if ($unitPrice !== null) {
                $currentVendorResults[] = [
                    'part_number' => $partNumber,
                    'unit_price' => $unitPrice,
                    'url' => $finding->productUrl,
                ];
                if ($finding->currency !== null) {
                    $currency = $finding->currency;
                }
            }
        }

        if ($currentVendorResults === []) {
            return null;
        }

        $result = [
            'success' => true,
            'current_vendor_results' => $currentVendorResults,
            'current_vendor_currency' => $currency,
            'alt_vendor_results' => $this->fetchAltVendorsFromApis($mpns, $quantity, 'digikey', $inventoryId),
            'source' => 'digikey_api',
        ];
        return $result;
    }

    /**
     * @param  array<int, string>  $mpns
     * @return array{success: true, current_vendor_results: array, current_vendor_currency: string, alt_vendor_results: array, source: string}|null
     */
    protected function lookupMouser(array $mpns, float $quantity, int $inventoryId): ?array
    {
        if (! $this->mouser->isEnabled()) {
            return null;
        }

        $currentVendorResults = [];
        $currency = 'USD';

        foreach ($mpns as $index => $partNumber) {
            $partNumber = trim((string) $partNumber);
            if ($partNumber === '') {
                continue;
            }
            $findings = $this->mouser->lookup($partNumber, $inventoryId);
            $finding = $this->bestMatchingFinding($findings, $partNumber);

            if ($finding === null) {
                continue;
            }
            $unitPrice = self::priceForQuantity($finding->priceBreaks, $quantity);
            if ($unitPrice === null) {
                $unitPrice = $finding->minUnitPrice;
            }
            if ($unitPrice !== null) {
                $currentVendorResults[] = [
                    'part_number' => $partNumber,
                    'unit_price' => $unitPrice,
                    'url' => $finding->productUrl,
                ];
                if ($finding->currency !== null) {
                    $currency = $finding->currency;
                }
            }
        }

        if ($currentVendorResults === []) {
            return null;
        }

        $result = [
            'success' => true,
            'current_vendor_results' => $currentVendorResults,
            'current_vendor_currency' => $currency,
            'alt_vendor_results' => $this->fetchAltVendorsFromApis($mpns, $quantity, 'mouser', $inventoryId),
            'source' => 'mouser_api',
        ];
        return $result;
    }

    /**
     * @param  array<int, string>  $mpns
     * @return array{success: true, current_vendor_results: array, current_vendor_currency: string, alt_vendor_results: array, source: string}|null
     */
    protected function lookupElement14(array $mpns, float $quantity, int $inventoryId): ?array
    {
        if (! $this->element14->isEnabled()) {
            return null;
        }

        $currentVendorResults = [];
        $currency = 'USD';

        foreach ($mpns as $partNumber) {
            $partNumber = trim((string) $partNumber);
            if ($partNumber === '') {
                continue;
            }
            $findings = $this->element14->lookup($partNumber, $inventoryId);
            $finding = $this->bestMatchingFinding($findings, $partNumber);

            if ($finding === null) {
                continue;
            }
            $unitPrice = self::priceForQuantity($finding->priceBreaks, $quantity);
            if ($unitPrice === null) {
                $unitPrice = $finding->minUnitPrice;
            }
            if ($unitPrice !== null) {
                $currentVendorResults[] = [
                    'part_number' => $partNumber,
                    'unit_price' => $unitPrice,
                    'url' => $finding->productUrl,
                ];
                if ($finding->currency !== null) {
                    $currency = $finding->currency;
                }
            }
        }

        if ($currentVendorResults === []) {
            return null;
        }

        $result = [
            'success' => true,
            'current_vendor_results' => $currentVendorResults,
            'current_vendor_currency' => $currency,
            'alt_vendor_results' => $this->fetchAltVendorsFromApis($mpns, $quantity, 'element14', $inventoryId),
            'source' => 'element14_api',
        ];
        return $result;
    }

    /**
     * Fetch alternative vendor pricing from DigiKey and/or Mouser API.
     * Skips the API for the current vendor. Caller always adds Gemini alt-vendor results separately.
     * - Current = DigiKey → this returns Mouser only (Gemini adds others).
     * - Current = Mouser → this returns DigiKey only (Gemini adds others).
     * - Current = other → this returns DigiKey + Mouser (Gemini adds others).
     *
     * @param  array<int, string>  $mpns
     * @param  int|null  $inventoryId  Optional. When set, logs "item_id X: DigiKey/Mouser API" when making requests.
     * @return array<int, array{part_number: string, vendors: array<int, array{vendor_name: string, unit_price: float, url: string|null}>}>
     */
    public function fetchAltVendorsFromApis(array $mpns, float $quantity, string $currentVendorName, ?int $inventoryId = null): array
    {
        $normalized = strtolower(trim($currentVendorName));
        $normalizedNoSpaces = str_replace(' ', '', $normalized);
        $isElement14 = $this->isElement14Vendor($normalized);
        $callDigiKey = ! str_contains($normalizedNoSpaces, 'digikey') && ! str_contains($normalized, 'digi-key') && ! str_contains($normalized, 'digi key');
        $callMouser = ! str_contains($normalized, 'mouser');
        $callElement14 = ! $isElement14;

        $byPart = [];
        foreach ($mpns as $partNumber) {
            $partNumber = trim((string) $partNumber);
            if ($partNumber === '') {
                continue;
            }
            $vendors = [];

            if ($callDigiKey && $this->digiKey->isEnabled()) {
                $findings = $this->digiKey->lookup($partNumber, null, $inventoryId);
                $finding = $this->bestMatchingFinding($findings, $partNumber);
                if ($finding !== null) {
                    $unitPrice = self::priceForQuantity($finding->priceBreaks, $quantity) ?? $finding->minUnitPrice;
                    if ($unitPrice !== null) {
                        $vendors[] = [
                            'vendor_name' => 'Digi-Key',
                            'unit_price' => $unitPrice,
                            'url' => $finding->productUrl,
                        ];
                    }
                }
            }

            if ($callMouser && $this->mouser->isEnabled()) {
                $findings = $this->mouser->lookup($partNumber, $inventoryId);
                $finding = $this->bestMatchingFinding($findings, $partNumber);
                if ($finding !== null) {
                    $unitPrice = self::priceForQuantity($finding->priceBreaks, $quantity) ?? $finding->minUnitPrice;
                    if ($unitPrice !== null) {
                        $vendors[] = [
                            'vendor_name' => 'Mouser',
                            'unit_price' => $unitPrice,
                            'url' => $finding->productUrl,
                        ];
                    }
                }
            }

            if ($callElement14 && $this->element14->isEnabled()) {
                $findings = $this->element14->lookup($partNumber, $inventoryId);
                $finding = $this->bestMatchingFinding($findings, $partNumber);
                if ($finding !== null) {
                    $unitPrice = self::priceForQuantity($finding->priceBreaks, $quantity) ?? $finding->minUnitPrice;
                    if ($unitPrice !== null) {
                        $vendors[] = [
                            'vendor_name' => 'Newark',
                            'unit_price' => $unitPrice,
                            'url' => $finding->productUrl,
                        ];
                    }
                }
            }

            if ($vendors !== []) {
                $byPart[] = ['part_number' => $partNumber, 'vendors' => $vendors];
            }
        }

        return $byPart;
    }

    /**
     * Merge API-sourced alt vendor results into an existing alt_vendor_results array (e.g. from Gemini).
     * For each part_number, appends API vendors; if the same vendor_name already exists, replaces with API data.
     *
     * @param  array<int, array{part_number: string, vendors: array<int, array{vendor_name: string, unit_price: float, url: string|null}>}>  $existing
     * @param  array<int, array{part_number: string, vendors: array<int, array{vendor_name: string, unit_price: float, url: string|null}>}>  $fromApi
     * @return array<int, array{part_number: string, vendors: array<int, array{vendor_name: string, unit_price: float, url: string|null}>}>
     */
    public static function mergeAltVendorResults(array $existing, array $fromApi): array
    {
        $keyed = [];
        foreach ($existing as $row) {
            $pn = trim((string) ($row['part_number'] ?? ''));
            if ($pn === '') {
                continue;
            }
            $keyed[$pn] = ['part_number' => $pn, 'vendors' => $row['vendors'] ?? []];
        }
        foreach ($fromApi as $row) {
            $pn = trim((string) ($row['part_number'] ?? ''));
            if ($pn === '') {
                continue;
            }
            $existingVendors = $keyed[$pn]['vendors'] ?? [];
            $byName = [];
            foreach ($existingVendors as $v) {
                $byName[strtolower(trim((string) ($v['vendor_name'] ?? '')))] = $v;
            }
            foreach ($row['vendors'] ?? [] as $v) {
                $name = strtolower(trim((string) ($v['vendor_name'] ?? '')));
                $byName[$name] = [
                    'vendor_name' => $v['vendor_name'] ?? '',
                    'unit_price' => (float) ($v['unit_price'] ?? 0),
                    'url' => isset($v['url']) && trim((string) $v['url']) !== '' ? trim((string) $v['url']) : null,
                ];
            }
            $keyed[$pn] = ['part_number' => $pn, 'vendors' => array_values($byName)];
        }
        return array_values($keyed);
    }

    /**
     * @param  array<int, PriceFindingData>  $findings
     */
    protected function bestMatchingFinding(array $findings, string $partNumber): ?PriceFindingData
    {
        $partLower = strtolower($partNumber);
        foreach ($findings as $f) {
            if ($f->matchedMpn !== null && strtolower($f->matchedMpn) === $partLower) {
                return $f;
            }
        }

        return $findings[0] ?? null;
    }
}
