<?php

declare(strict_types=1);

use App\Models\Action;
use App\Models\PriceFinding;
use App\Models\ResearchTask;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$outDir = realpath(__DIR__.'/../../phase5_1') ?: (__DIR__.'/../../phase5_1');
if (! is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$laravelOut = $outDir.'/laravel_output';
if (! is_dir($laravelOut)) {
    mkdir($laravelOut, 0777, true);
}

$latestBatchId = ResearchTask::query()->orderByDesc('id')->value('batch_id');
$batchTasks = ResearchTask::query()->where('batch_id', $latestBatchId)->pluck('id');

$summary = [
    'batch_id' => $latestBatchId,
    'queue_total' => ResearchTask::query()->where('batch_id', $latestBatchId)->count(),
    'status_counts' => ResearchTask::query()->where('batch_id', $latestBatchId)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all(),
    'task_type_counts' => ResearchTask::query()->where('batch_id', $latestBatchId)->selectRaw('task_type, count(*) as c')->groupBy('task_type')->pluck('c', 'task_type')->all(),
    'price_findings_total' => PriceFinding::query()->whereIn('research_task_id', $batchTasks)->count(),
    'provider_counts' => PriceFinding::query()->whereIn('research_task_id', $batchTasks)->selectRaw('provider, count(*) as c')->groupBy('provider')->pluck('c', 'provider')->all(),
    'actions_total' => Action::query()->whereIn('research_task_id', $batchTasks)->count(),
    'modeled_savings_total' => (float) Action::query()->whereIn('research_task_id', $batchTasks)->sum('estimated_savings'),
];

$rows = DB::table('research_tasks as rt')
    ->join('items as i', 'i.id', '=', 'rt.item_id')
    ->join('suppliers as s', 's.id', '=', 'rt.supplier_id')
    ->where('rt.batch_id', $latestBatchId)
    ->select('rt.task_type', 'rt.status', 'i.internal_part_number as item', 's.name as vendor')
    ->orderBy('rt.id')
    ->get()
    ->map(fn ($r) => (array) $r)
    ->all();

file_put_contents($laravelOut.'/summary.json', json_encode($summary, JSON_PRETTY_PRINT));
file_put_contents($laravelOut.'/queue_rows.json', json_encode($rows, JSON_PRETTY_PRINT));

echo "Wrote: {$laravelOut}/summary.json\n";
echo "Wrote: {$laravelOut}/queue_rows.json\n";
