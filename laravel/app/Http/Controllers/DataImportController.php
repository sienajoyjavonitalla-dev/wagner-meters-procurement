<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDataImportRequest;
use App\Jobs\ProcessImportJob;
use App\Models\DataImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DataImportController extends Controller
{
    public function create(): View
    {
        $recent = DataImport::where('type', 'full')->orderByDesc('id')->limit(5)->get();

        return view('data-import.create', ['recentImports' => $recent]);
    }

    public function store(StoreDataImportRequest $request): RedirectResponse|JsonResponse
    {
        $dir = 'imports/'.uniqid('run_', true);
        $disk = Storage::disk('imports');
        $file = $request->file('inventory');
        $extension = $file->getClientOriginalExtension();
        $inventoryPath = $disk->putFileAs($dir, $file, 'inventory.'.$extension);

        $import = DataImport::create([
            'type' => 'full',
            'user_id' => $request->user() ? $request->user()->id : null,
            'file_names' => [
                'inventory' => $file->getClientOriginalName(),
            ],
            'row_counts' => [],
            'status' => 'pending',
        ]);

        ProcessImportJob::dispatch($import, $inventoryPath);

        $message = 'Import queued. Refresh the page in a moment to see status.';
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('data-import.create')->with('success', $message);
    }
}
