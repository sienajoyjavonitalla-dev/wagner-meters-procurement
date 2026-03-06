<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDataImportRequest;
use App\Jobs\ProcessImportJob;
use App\Models\DataImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $inventoryPath = $request->file('inventory')->storeAs($dir, 'inventory.'.$request->file('inventory')->getClientOriginalExtension());
        $vendorPriorityPath = $request->file('vendor_priority')->storeAs($dir, 'vendor_priority.'.$request->file('vendor_priority')->getClientOriginalExtension());
        $itemSpreadPath = $request->file('item_spread')->storeAs($dir, 'item_spread.'.$request->file('item_spread')->getClientOriginalExtension());
        $mpnMapPath = $request->hasFile('mpn_map') && $request->file('mpn_map')->isValid()
            ? $request->file('mpn_map')->storeAs($dir, 'mpn_map.'.$request->file('mpn_map')->getClientOriginalExtension())
            : null;

        $import = DataImport::create([
            'type' => 'full',
            'user_id' => $request->user()?->id,
            'file_names' => [
                'inventory' => $request->file('inventory')->getClientOriginalName(),
                'vendor_priority' => $request->file('vendor_priority')->getClientOriginalName(),
                'item_spread' => $request->file('item_spread')->getClientOriginalName(),
                'mpn_map' => $mpnMapPath ? $request->file('mpn_map')->getClientOriginalName() : null,
            ],
            'row_counts' => [],
            'status' => 'pending',
        ]);

        ProcessImportJob::dispatch($import, $inventoryPath, $vendorPriorityPath, $itemSpreadPath, $mpnMapPath);

        $message = 'Import queued. Refresh the page in a moment to see status.';
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('data-import.create')->with('success', $message);
    }
}
