<?php

namespace App\Services;

class ResearchMatchHelper
{
    /**
     * Normalize part number for comparison: alphanumeric uppercase.
     */
    public static function normalizePart(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));
    }

    /**
     * Match score between target (e.g. mapped MPN) and matched (e.g. distributor result). 0..1.
     */
    public static function matchScore(string $targetMpn, string $matchedPart): float
    {
        $target = self::normalizePart($targetMpn);
        $matched = self::normalizePart($matchedPart);
        if ($target === '' || $matched === '') {
            return 0.0;
        }
        if ($target === $matched) {
            return 1.0;
        }
        if (strlen($target) >= 8 && (str_contains($matched, $target) || str_contains($target, $matched))) {
            return 0.94;
        }
        similar_text($target, $matched, $pct);
        return round($pct / 100, 4);
    }

    /**
     * Extract candidate MPN-like tokens from item id + description (for needs_mapping fallback).
     *
     * @return array<int, string>
     */
    public static function extractCandidateMpns(string $itemId, string $description, int $maxCandidates = 5): array
    {
        $text = strtoupper($itemId . ' ' . $description);
        $text = str_replace([',', '(', ')'], ' ', $text);
        if (preg_match_all('/\b[A-Z0-9][A-Z0-9\-_\/\.]{3,}\b/', $text, $m) !== 1) {
            $m = [[]];
        }
        $tokens = $m[0] ?? [];
        $drop = [
            'MODEL', 'REV', 'BOARD', 'PCA', 'PCBA', 'SENSOR', 'ASSY', 'UNTESTED',
            'MANUAL', 'INSTRUCTION', 'OUTER', 'INNER', 'LABOR',
        ];
        $results = [];
        foreach ($tokens as $token) {
            if (in_array($token, $drop, true) || strlen($token) < 5) {
                continue;
            }
            if (! preg_match('/[A-Z]/', $token) || ! preg_match('/\d/', $token)) {
                continue;
            }
            if (! in_array($token, $results, true)) {
                $results[] = $token;
            }
            if (count($results) >= $maxCandidates) {
                break;
            }
        }
        $itemUpper = strtoupper($itemId);
        if ($itemUpper !== '' && ! in_array($itemUpper, $results, true)) {
            $results[] = $itemUpper;
        }
        return array_slice($results, 0, $maxCandidates);
    }
}
