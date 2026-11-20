<?php

namespace App\Http\Controllers\Api;

use App\Services\GadSourceExport;
use Illuminate\Http\JsonResponse;

class GadSourceExportController
{
    public function __invoke(GadSourceExport $export): JsonResponse
    {
        return response()->json($export->snapshot())->header('Cache-Control', 'no-store');
    }
}
