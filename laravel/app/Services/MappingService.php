<?php

namespace App\Services;

use App\Models\Mapping;

class MappingService
{
    /** @var array<int, array{mpn: string, mapping_status: string, lookup_mode: string, manufacturer: ?string, notes: ?string}> */
    protected array $index = [];

    public function loadForImport(int $dataImportId): void
    {
        $this->index = [];

        $rows = Mapping::query()
            ->where('data_import_id', $dataImportId)
            ->get();

        foreach ($rows as $m) {
            $itemId = $m->item_id;
            $status = $this->normalizeStatus($m);
            $lookupMode = $this->normalizeLookupMode($m);

            if (! isset($this->index[$itemId])) {
                $this->index[$itemId] = [
                    'mpn' => $m->mpn ?? '',
                    'mapping_status' => $status,
                    'lookup_mode' => $lookupMode,
                    'manufacturer' => $m->manufacturer,
                    'notes' => $m->notes,
                ];
                continue;
            }

            if ($status === 'mapped' && ($this->index[$itemId]['mapping_status'] ?? '') !== 'mapped') {
                $this->index[$itemId] = [
                    'mpn' => $m->mpn ?? '',
                    'mapping_status' => $status,
                    'lookup_mode' => $lookupMode,
                    'manufacturer' => $m->manufacturer,
                    'notes' => $m->notes,
                ];
            }
        }
    }

    public function getMappedMpn(int $itemId): ?string
    {
        $entry = $this->index[$itemId] ?? null;
        if (! $entry) {
            return null;
        }
        $mpn = trim((string) ($entry['mpn'] ?? ''));
        return $mpn === '' ? null : $mpn;
    }

    /**
     * Strict status: mapped | non_catalog | needs_mapping.
     */
    public function getMappingStatus(int $itemId): string
    {
        $entry = $this->index[$itemId] ?? null;
        if (! $entry) {
            return 'needs_mapping';
        }
        return $entry['mapping_status'];
    }

    public function isNonCatalog(int $itemId): bool
    {
        return $this->getMappingStatus($itemId) === 'non_catalog';
    }

    public function getLookupMode(int $itemId): string
    {
        $entry = $this->index[$itemId] ?? null;
        if (! $entry) {
            return 'catalog_lookup';
        }
        return $entry['lookup_mode'];
    }

    public function getEntry(int $itemId): ?array
    {
        return $this->index[$itemId] ?? null;
    }

    public function hasMapping(int $itemId): bool
    {
        return isset($this->index[$itemId]);
    }

    /**
     * Normalize to strict: mapped | non_catalog | needs_mapping.
     */
    protected function normalizeStatus(Mapping $m): string
    {
        $lookupMode = strtolower(trim((string) ($m->lookup_mode ?? '')));
        if (in_array($lookupMode, ['non_catalog', 'noncatalog', 'custom'], true)) {
            return 'non_catalog';
        }

        $status = strtolower(trim((string) ($m->mapping_status ?? '')));
        $mpn = trim((string) ($m->mpn ?? ''));

        if ($status === 'non_catalog' || $mpn === '' && in_array($status, ['non_catalog', 'noncatalog'], true)) {
            return 'non_catalog';
        }

        if ($mpn !== '' && in_array($status, ['mapped', 'verified'], true)) {
            return 'mapped';
        }

        if ($mpn !== '') {
            return 'mapped';
        }

        return 'needs_mapping';
    }

    protected function normalizeLookupMode(Mapping $m): string
    {
        $status = $this->normalizeStatus($m);
        if ($status === 'non_catalog') {
            return 'non_catalog';
        }
        return 'catalog_lookup';
    }
}
