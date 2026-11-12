<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CesEnrollmentSummary;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CesEnrollmentSummaryController extends Controller
{
    public function __invoke(CesEnrollmentSummary $summary): JsonResponse
    {
        try {
            return response()->json($summary->get());
        } catch (QueryException|\PDOException $exception) {
            Log::warning('CES campus summary could not be retrieved.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'CES campus data is temporarily unavailable.',
            ], 503);
        }
    }
}
