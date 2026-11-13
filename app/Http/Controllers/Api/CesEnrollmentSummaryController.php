<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Integrations\CesSystemIntegration;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CesEnrollmentSummaryController extends Controller
{
    public function __invoke(CesSystemIntegration $integration): JsonResponse
    {
        try {
            $data = $integration->fetchLocalData();
        } catch (QueryException|\PDOException $exception) {
            Log::warning('CES enrollment snapshot could not be retrieved.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'CES enrollment snapshot is temporarily unavailable.',
            ], 503);
        }

        if (($data['source'] ?? null) !== 'live') {
            return response()->json([
                'message' => is_string($data['summary'] ?? null)
                    ? $data['summary']
                    : 'CES enrollment snapshot is unavailable.',
            ], 503);
        }

        return response()->json($data);
    }
}
