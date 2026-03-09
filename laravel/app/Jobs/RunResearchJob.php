<?php

namespace App\Jobs;

use App\DTO\PriceFindingData;
use App\Models\PriceFinding;
use App\Models\ResearchRun;
use App\Models\ResearchTask;
use App\Services\ClaudeResearchService;
use App\Services\DigiKeyClient;
use App\Services\FxSnapshotService;
use App\Services\AuditLogService;
use App\Services\AppSettingsService;
use App\Services\MappingService;
use App\Services\MouserClient;
use App\Services\NexarClient;
use App\Services\PostProcessResearchService;
use App\Services\ResearchMatchHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $batchId = null,
        public int $limit = 50,
        public bool $useClaudeFallback = true,
        public ?int $researchRunId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(
        DigiKeyClient $digiKey,
        MouserClient $mouser,
        NexarClient $nexar,
        ClaudeResearchService $claude,
        MappingService $mappingService,
        PostProcessResearchService $postProcess,
        FxSnapshotService $fxSnapshot,
        AppSettingsService $settings,
        AuditLogService $auditLog,
    ): void {
        $run = $this->researchRunId ? ResearchRun::find($this->researchRunId) : null;
        if ($run) {
            $run->update(['status' => 'running', 'message' => 'Research job started.']);
        }
        $auditLog->log('research.run.started', null, 'research_run', $run?->id, [
            'batch_id' => $this->batchId,
            'limit' => $this->limit,
            'use_claude' => $this->useClaudeFallback,
        ]);

        try {
            $this->runResearch($digiKey, $mouser, $nexar, $claude, $mappingService, $settings);
            $actionsCount = $postProcess->process();
            $fxSnapshot->capture();

            if ($run) {
                $run->update([
                    'status' => 'completed',
                    'message' => "Post-process: {$actionsCount} actions upserted. FX snapshot captured.",
                    'completed_at' => now(),
                ]);
            }
            $auditLog->log('research.run.completed', null, 'research_run', $run?->id, [
                'actions_upserted' => $actionsCount,
            ]);
        } catch (Throwable $e) {
            if ($run) {
                $run->update([
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
            }
            $auditLog->log('research.run.failed', null, 'research_run', $run?->id, [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function runResearch(
        DigiKeyClient $digiKey,
        MouserClient $mouser,
        NexarClient $nexar,
        ClaudeResearchService $claude,
        MappingService $mappingService,
        AppSettingsService $settings,
    ): void {
        $researchSettings = $settings->getResearchSettings();
        $strictMapping = (bool) ($researchSettings['strict_mapping'] ?? true);
        $minMatchScore = (float) ($researchSettings['min_match_score'] ?? 0.9);
        $claudeBatchSize = (int) ($researchSettings['claude_batch_size'] ?? 50);

        $query = ResearchTask::query()
            ->where('status', 'pending')
            ->with(['item', 'supplier']);

        if ($this->batchId !== null && $this->batchId !== '') {
            $query->where('batch_id', $this->batchId);
        }

        $tasks = $query->orderBy('priority')->limit($this->limit)->get();

        if ($tasks->isEmpty()) {
            return; // run will be updated by caller (post-process still runs)
        }

        $dataImportId = $tasks->first()->item?->data_import_id;
        if ($dataImportId === null) {
            return;
        }

        $mappingService->loadForImport($dataImportId);

        $providers = array_filter([
            $digiKey->isEnabled() ? $digiKey : null,
            $mouser->isEnabled() ? $mouser : null,
            $nexar->isEnabled() ? $nexar : null,
        ]);
        $claudeEnabled = $this->useClaudeFallback && $claude->isEnabled();
        $agentCount = 0;

        foreach ($tasks as $task) {
            $item = $task->item;
            $supplier = $task->supplier;
            if (! $item || ! $supplier) {
                $task->update(['status' => 'needs_research', 'notes' => 'Missing item or supplier.']);
                continue;
            }

            $itemId = $task->item_id;
            $vendorName = $supplier->name;
            $description = (string) ($task->description ?? $item->description ?? '');
            $itemPartNumber = (string) ($item->internal_part_number ?? '');
            $targetMpn = $mappingService->getMappedMpn($itemId) ?? '';

            if ($mappingService->isNonCatalog($itemId)) {
                $task->update([
                    'status' => 'skipped_non_catalog',
                    'notes' => 'Marked non-catalog; API lookup skipped.',
                ]);
                continue;
            }

            if ($strictMapping && $mappingService->getMappingStatus($itemId) !== 'mapped') {
                $task->update([
                    'status' => 'needs_mapping',
                    'notes' => 'No verified MPN mapping. Update mappings before pricing research.',
                ]);
                continue;
            }

            $candidates = $mappingService->getCandidateMpns($itemId, $itemPartNumber, $description, $strictMapping);
            if ($candidates === []) {
                $task->update([
                    'status' => 'needs_mapping',
                    'notes' => 'No candidate MPN for lookup.',
                ]);
                continue;
            }

            $foundAccepted = false;
            $anyCandidate = false;

            foreach ($candidates as $queryMpn) {
                foreach ($providers as $client) {
                    $findings = $client->lookup($queryMpn);
                    foreach ($findings as $finding) {
                        $this->persistFinding($task, $finding, $targetMpn ?: $queryMpn, $strictMapping, $minMatchScore);
                        $anyCandidate = true;
                        if ($this->isAccepted($finding, $targetMpn ?: $queryMpn, $strictMapping, $minMatchScore)) {
                            $foundAccepted = true;
                        }
                    }
                }
                if ($foundAccepted && $strictMapping) {
                    break;
                }
            }

            if (! $foundAccepted && $claudeEnabled && $agentCount < $claudeBatchSize && $candidates !== []) {
                $agentCount++;
                $result = $claude->debugLookup($vendorName, $itemPartNumber, $description, $targetMpn ?: $candidates[0]);
                foreach ($result['findings'] ?? [] as $finding) {
                    $this->persistFinding($task, $finding, $targetMpn ?: $candidates[0], $strictMapping, $minMatchScore);
                    if ($this->isAccepted($finding, $targetMpn ?: $candidates[0], $strictMapping, $minMatchScore)) {
                        $foundAccepted = true;
                    }
                }
            }

            if ($foundAccepted) {
                $task->update(['status' => 'researched', 'notes' => null]);
            } else {
                $notes = 'No API match found.';
                if ($strictMapping && $mappingService->getMappingStatus($itemId) === 'mapped' && $anyCandidate) {
                    $notes = 'Candidates found but none passed strict MPN match.';
                }
                $task->update(['status' => 'needs_research', 'notes' => $notes]);
            }
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        $run = $this->researchRunId ? ResearchRun::find($this->researchRunId) : null;
        if ($run) {
            $run->update([
                'status' => 'failed',
                'message' => $exception ? $exception->getMessage() : 'Job failed.',
                'completed_at' => now(),
            ]);
        }
    }

    protected function persistFinding(
        ResearchTask $task,
        PriceFindingData $finding,
        string $targetMpn,
        bool $strictMapping,
        float $minMatchScore,
    ): void {
        $matchedMpn = $finding->matchedMpn ?? '';
        $score = ResearchMatchHelper::matchScore($targetMpn, $matchedMpn);
        $accepted = ! $strictMapping || ($targetMpn !== '' && $score >= $minMatchScore);

        $attrs = $finding->toPriceFindingAttributes();
        $attrs['research_task_id'] = $task->id;
        $attrs['match_score'] = $score;
        $attrs['accepted'] = $accepted;

        PriceFinding::create($attrs);
    }

    protected function isAccepted(PriceFindingData $finding, string $targetMpn, bool $strictMapping, float $minMatchScore): bool
    {
        $matched = $finding->matchedMpn ?? '';
        $score = ResearchMatchHelper::matchScore($targetMpn, $matched);
        return ! $strictMapping || ($targetMpn !== '' && $score >= $minMatchScore);
    }
}
