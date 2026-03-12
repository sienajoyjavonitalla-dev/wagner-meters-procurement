<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache of API/AI responses keyed by cache_key + source so we avoid repeated calls.
 * - DigiKey/Mouser: cache_key = part_number (one row per MPN per source).
 * - Gemini: cache_key = hash(vendor, product_line, sorted_mpns, quantity, type).
 */
class ResearchedMpn extends Model
{
    protected $table = 'researched_mpn';

    protected $fillable = [
        'cache_key',
        'source',
        'response_json',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'response_json' => 'array',
        ];
    }

    public const SOURCE_DIGIKEY = 'digikey';
    public const SOURCE_MOUSER = 'mouser';
    public const SOURCE_GEMINI = 'gemini';

    public static function getCached(string $cacheKey, string $source): ?array
    {
        $row = self::query()
            ->where('cache_key', $cacheKey)
            ->where('source', $source)
            ->first();

        return $row && is_array($row->response_json) ? $row->response_json : null;
    }

    public static function setCached(string $cacheKey, string $source, array $responseJson, ?string $url = null): void
    {
        $row = self::query()->firstOrNew(['cache_key' => $cacheKey, 'source' => $source]);
        $row->response_json = $responseJson;
        $row->url = $url;
        $row->save();
    }
}
