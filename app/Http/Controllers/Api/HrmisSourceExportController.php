<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HrmisWarehouseSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmisSourceExportController extends Controller
{
    private const DATASETS = ['employees', 'departments', 'positions', 'education'];

    public function __invoke(Request $request, string $dataset, HrmisWarehouseSynchronizer $synchronizer): JsonResponse
    {
        abort_unless(in_array($dataset, self::DATASETS, true), 404);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);
        $page = (int) ($validated['page'] ?? 1);
        $perPage = min(
            1000,
            (int) ($validated['per_page'] ?? config('source_apis.hrmis.page_size', 1000))
        );
        $records = $synchronizer->exportSourceData()[$dataset];
        $total = count($records);
        $lastPage = max(1, (int) ceil($total / $perPage));

        return response()->json([
            'data' => array_slice($records, ($page - 1) * $perPage, $perPage),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }
}
