<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Services\QamsSystemData;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class Analytics extends Controller
{
    public function __construct(private readonly QamsSystemData $systemData) {}

    public function index()
    {
        return view('personal.dashboard', [
            'systems' => $this->systemData->snapshot(),
            'categories' => config('qams.categories', []),
            'shortNames' => config('qams.short_names', []),
        ]);
    }

    public function systems(): JsonResponse
    {
        return response()->json([
            'systems' => $this->systemData->all(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function showSystem(string $system)
    {
        $systemDefinition = $this->systemData->definition($system);

        abort_if($systemDefinition === null, 404);

        $canonicalSlug = Str::slug($systemDefinition['name']);

        if ($system !== $canonicalSlug) {
            return redirect()->route('qams.system', ['system' => $canonicalSlug]);
        }

        $systemData = $systemDefinition['key'] === 'system-17'
            ? $systemDefinition
            : $this->systemData->find($system);

        return view('personal.system-metrics', [
            'system' => $systemData,
            'cesAnalytics' => $systemDefinition['key'] === 'system-17' ? config('qams.ces_analytics') : null,
        ]);
    }
}
