<?php

namespace App\Services;

use App\Models\DataImport;
use App\Models\Inventory;
use App\Models\ResearchRun;

class ProcurementReportingService
{
    protected function currentImportId(): ?int
    {
        return DataImport::currentFull()->value('id');
    }

    /**
     * Build summary KPIs for the current import (queue status, provider hits, savings per vendor).
     *
     * @return array{
     *   queue_status_counts: array{researched: int, pending: int},
     *   provider_hit_counts: array<string,int>,
     *   savings_potential_per_vendor: array<int, array{vendor_name: string, savings_total: float}>
     * }
     */
    public function buildSummary(): array
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return [
                'queue_status_counts' => ['researched' => 0, 'pending' => 0],
                'provider_hit_counts' => [],
                'savings_potential_per_vendor' => [],
            ];
        }

        $inventories = Inventory::query()
            ->where('data_import_id', $importId)
            ->with(['mpns', 'altVendors'])
            ->get();

        $researched = $inventories->whereNotNull('research_completed_at')->count();
        $pending = $inventories->whereNull('research_completed_at')->count();
        $queueStatusCounts = [
            'researched' => $researched,
            'pending' => $pending,
        ];

        $geminiTotal = (int) ResearchRun::query()->sum('gemini_hits');
        $providerHitCounts = $geminiTotal > 0 ? ['gemini' => $geminiTotal] : [];

        $savingsByVendor = [];
        foreach ($inventories as $inv) {
            $current = (float) ($inv->unit_cost ?? 0);
            $lowestMpn = $inv->mpns->whereNotNull('unit_price')->min('unit_price');
            $lowestAlt = $inv->altVendors->min('unit_price');
            $lowest = null;
            if ($lowestMpn !== null && $lowestAlt !== null) {
                $lowest = min((float) $lowestMpn, (float) $lowestAlt);
            } elseif ($lowestMpn !== null) {
                $lowest = (float) $lowestMpn;
            } elseif ($lowestAlt !== null) {
                $lowest = (float) $lowestAlt;
            }

            if ($lowest !== null && $current > $lowest) {
                $vendor = (string) ($inv->vendor_name ?? 'Unknown');
                $savingsByVendor[$vendor] = ($savingsByVendor[$vendor] ?? 0) + ($current - $lowest);
            }
        }

        arsort($savingsByVendor);
        $savingsPotentialPerVendor = [];
        foreach (array_slice($savingsByVendor, 0, 10, true) as $vendorName => $total) {
            $savingsPotentialPerVendor[] = [
                'vendor_name' => $vendorName,
                'savings_total' => round((float) $total, 4),
            ];
        }

        return [
            'queue_status_counts' => $queueStatusCounts,
            'provider_hit_counts' => $providerHitCounts,
            'savings_potential_per_vendor' => $savingsPotentialPerVendor,
        ];
    }

    /**
     * Build analytics for the current import: savings per vendor + daily savings trend.
     *
     * @return array{
     *   top_suppliers_by_savings: array<int, array{supplier_name: string, savings_total: float}>,
     *   daily_modeled_savings: array<int, array{day: string, savings_total: float}>
     * }
     */
    public function buildAnalytics(): array
    {
        $importId = $this->currentImportId();
        if ($importId === null) {
            return [
                'top_suppliers_by_savings' => [],
                'daily_modeled_savings' => [],
            ];
        }

        $inventories = Inventory::query()
            ->where('data_import_id', $importId)
            ->whereNotNull('research_completed_at')
            ->with(['mpns', 'altVendors'])
            ->get();

        $savingsByVendor = [];
        foreach ($inventories as $inv) {
            $current = (float) ($inv->unit_cost ?? 0);
            $lowestMpn = $inv->mpns->whereNotNull('unit_price')->min('unit_price');
            $lowestAlt = $inv->altVendors->min('unit_price');
            $lowest = null;
            if ($lowestMpn !== null && $lowestAlt !== null) {
                $lowest = min((float) $lowestMpn, (float) $lowestAlt);
            } elseif ($lowestMpn !== null) {
                $lowest = (float) $lowestMpn;
            } elseif ($lowestAlt !== null) {
                $lowest = (float) $lowestAlt;
            }

            if ($lowest !== null && $current > $lowest) {
                $vendor = (string) ($inv->vendor_name ?? 'Unknown');
                $savingsByVendor[$vendor] = ($savingsByVendor[$vendor] ?? 0) + ($current - $lowest);
            }
        }

        arsort($savingsByVendor);
        $topSuppliers = [];
        foreach (array_slice($savingsByVendor, 0, 10, true) as $vendorName => $total) {
            $topSuppliers[] = [
                'supplier_name' => $vendorName,
                'savings_total' => round((float) $total, 4),
            ];
        }

        $byDay = [];
        foreach ($inventories as $inv) {
            $day = $inv->research_completed_at
                ? $inv->research_completed_at->format('Y-m-d')
                : null;
            if ($day === null) {
                continue;
            }
            $current = (float) ($inv->unit_cost ?? 0);
            $lowestMpn = $inv->mpns->whereNotNull('unit_price')->min('unit_price');
            $lowestAlt = $inv->altVendors->min('unit_price');
            $lowest = null;
            if ($lowestMpn !== null && $lowestAlt !== null) {
                $lowest = min((float) $lowestMpn, (float) $lowestAlt);
            } elseif ($lowestMpn !== null) {
                $lowest = (float) $lowestMpn;
            } elseif ($lowestAlt !== null) {
                $lowest = (float) $lowestAlt;
            }
            $savings = ($lowest !== null && $current > $lowest) ? ($current - $lowest) : 0;
            $byDay[$day] = ($byDay[$day] ?? 0) + $savings;
        }

        ksort($byDay);
        $dailySavings = [];
        foreach ($byDay as $day => $total) {
            $dailySavings[] = ['day' => $day, 'savings_total' => round((float) $total, 4)];
        }

        return [
            'top_suppliers_by_savings' => $topSuppliers,
            'daily_modeled_savings' => $dailySavings,
        ];
    }
}

