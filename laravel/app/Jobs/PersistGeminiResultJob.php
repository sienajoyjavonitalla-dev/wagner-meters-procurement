<?php

namespace App\Jobs;

use App\Models\Inventory;
use App\Services\GeminiResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PersistGeminiResultJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $inventoryId,
        public array $result,
    ) {
        $this->onQueue('default');
    }

    public function handle(GeminiResearchService $gemini): void
    {
        $inventory = Inventory::find($this->inventoryId);
        if (! $inventory) {
            Log::warning('PersistGeminiResultJob: inventory not found', ['inventory_id' => $this->inventoryId]);

            return;
        }

        if (! ($this->result['success'] ?? false)) {
            Log::warning('PersistGeminiResultJob: result not successful, skipping', ['inventory_id' => $this->inventoryId]);

            return;
        }

        $gemini->persistLookupResult($inventory, $this->result);
    }
}
