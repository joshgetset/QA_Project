<?php

namespace App\Http\Controllers;

use App\Exceptions\CesAnalyticsSnapshotUnavailable;
use App\Services\CesEnrollmentAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CesAnalyticsController extends Controller
{
    public function __invoke(Request $request, CesEnrollmentAnalytics $analytics): JsonResponse
    {
        $filters = $request->validate([
            'campus' => ['nullable', 'string', Rule::in(array_keys(config('ces.campuses', [])))],
            'college' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'regex:/^\d{4}$/D'],
            'semester' => ['nullable', Rule::in(['1', '2', '9'])],
        ]);

        try {
            return response()->json($analytics->get($filters));
        } catch (CesAnalyticsSnapshotUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }
}
