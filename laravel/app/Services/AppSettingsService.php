<?php

namespace App\Services;

use App\Models\SystemSetting;
use Throwable;

class AppSettingsService
{
    public const RESEARCH_KEY = 'procurement.research';

    public function getResearchSettings(): array
    {
        $defaults = [
            'strict_mapping' => (bool) config('procurement.research.strict_mapping', true),
            'min_match_score' => (float) config('procurement.research.min_match_score', 0.9),
            'claude_batch_size' => (int) config('procurement.research.claude_batch_size', 50),
            'top_vendors' => (int) config('procurement.research.top_vendors', 20),
            'items_per_vendor' => (int) config('procurement.research.items_per_vendor', 50),
            'top_spread_items' => (int) config('procurement.research.top_spread_items', 100),
            'nightly_enabled' => (bool) config('procurement.schedule.nightly_enabled', false),
            'nightly_time' => (string) config('procurement.schedule.nightly_time', '01:00'),
        ];

        try {
            $saved = SystemSetting::query()->where('key', self::RESEARCH_KEY)->value('value_json');
        } catch (Throwable) {
            return $defaults;
        }
        if (! is_array($saved)) {
            return $defaults;
        }

        return array_merge($defaults, $saved);
    }

    public function updateResearchSettings(array $input): array
    {
        $current = $this->getResearchSettings();
        $next = array_merge($current, $input);

        $next['strict_mapping'] = (bool) ($next['strict_mapping'] ?? true);
        $next['min_match_score'] = max(0.0, min(1.0, (float) ($next['min_match_score'] ?? 0.9)));
        $next['claude_batch_size'] = max(1, min(500, (int) ($next['claude_batch_size'] ?? 50)));
        $next['top_vendors'] = max(1, min(200, (int) ($next['top_vendors'] ?? 20)));
        $next['items_per_vendor'] = max(1, min(500, (int) ($next['items_per_vendor'] ?? 50)));
        $next['top_spread_items'] = max(1, min(1000, (int) ($next['top_spread_items'] ?? 100)));
        $next['nightly_enabled'] = (bool) ($next['nightly_enabled'] ?? false);
        $next['nightly_time'] = preg_match('/^\d{2}:\d{2}$/', (string) ($next['nightly_time'] ?? '01:00'))
            ? (string) $next['nightly_time']
            : '01:00';

        SystemSetting::query()->updateOrCreate(
            ['key' => self::RESEARCH_KEY],
            ['value_json' => $next]
        );

        return $next;
    }
}
