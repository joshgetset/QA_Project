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
            'systems' => $this->systemData->all(),
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
        $systemData = $this->systemData->find($system);

        abort_if($systemData === null, 404);

        $canonicalSlug = Str::slug($systemData['name']);

        if ($system !== $canonicalSlug) {
            return redirect()->route('qams.system', ['system' => $canonicalSlug]);
        }

        return view('personal.system-metrics', [
            'system' => $systemData,
            'cesAnalytics' => $systemData['key'] === 'system-17' ? config('qams.ces_analytics') : null,
        ]);
    }
}
