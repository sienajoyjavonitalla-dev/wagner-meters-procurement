<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResearchedMpn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchedMpnController extends Controller
{
    /**
     * List researched MPN cache rows (paginated, with optional filters).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 25)));
        $query = ResearchedMpn::query();

        if ($search = trim((string) $request->input('q', ''))) {
            $query->where('cache_key', 'like', '%'.$search.'%');
        }

        if ($source = trim((string) $request->input('source', ''))) {
            $query->where('source', $source);
        }

        $query->orderByDesc('updated_at');

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function (ResearchedMpn $row) {
            return [
                'id' => $row->id,
                'cache_key' => $row->cache_key,
                'source' => $row->source,
                'url' => $row->url,
                'updated_at' => $row->updated_at ? $row->updated_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}

