<?php

namespace App\Services;

use App\Models\Action;
use App\Models\ResearchTask;

class PostProcessResearchService
{
    public function __construct()
    {
    }

    /**
     * For each researched task with findings, pick best match and upsert action.
     */
    public function process(): int
    {
        $strict = config('procurement.research.strict_mapping', true);

        $tasks = ResearchTask::query()
            ->whereHas('priceFindings')
            ->with(['priceFindings', 'action'])
            ->get();

        $count = 0;
        foreach ($tasks as $task) {
            $findings = $task->priceFindings;
            if ($findings->isEmpty()) {
                continue;
            }

            $candidates = $findings->filter(fn ($f) => $f->min_unit_price !== null);
            if ($strict) {
                $candidates = $candidates->where('accepted', true);
            }
            $best = $candidates->sortBy('min_unit_price')->first();
            if (! $best) {
                continue;
            }

            $avgUnit = (float) ($task->avg_unit_cost_12m ?? 0);
            $bestPrice = (float) $best->min_unit_price;
            $qty = (float) ($task->qty_12m ?? 0);
            $estimatedSavings = max(0, ($avgUnit - $bestPrice) * $qty);
            $spend12m = (float) ($task->spend_12m ?? 0);
            $priorityScore = $estimatedSavings + $spend12m * 0.03;

            $actionType = $this->mapActionType($task->task_type);

            $task->action()->updateOrCreate(
                ['research_task_id' => $task->id],
                [
                    'estimated_savings' => round($estimatedSavings, 4),
                    'action_type' => $actionType,
                    'priority_score' => round($priorityScore, 4),
                    'approval_status' => 'pending',
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function mapActionType(string $taskType): string
    {
        return match ($taskType) {
            'pricing_benchmark' => 'pricing_benchmark',
            'alternate_part' => 'alternate_part',
            default => $taskType,
        };
    }
}
