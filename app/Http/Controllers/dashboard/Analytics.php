<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Services\GadWarehouseAnalytics;
use App\Services\HrmisWorkforceAnalytics;
use App\Services\QamsSystemData;
use App\Services\StudentTeacherRatio;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PDOException;

class Analytics extends Controller
{
    public function __construct(
        private readonly QamsSystemData $systemData,
        private readonly HrmisWorkforceAnalytics $workforce
    ) {}

    public function index()
    {
        return view('personal.dashboard', [
            'systems' => $this->systemData->snapshot(),
            'universityPersonnel' => $this->universityPersonnel(),
            'categories' => config('qams.categories', []),
            'shortNames' => config('qams.short_names', []),
        ]);
    }

    public function systems(): JsonResponse
    {
        return response()->json([
            'systems' => $this->systemData->all(),
            'university_personnel' => $this->universityPersonnel(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function universityPersonnel(): ?int
    {
        try {
            return $this->workforce->get()['summary'][0]['total'] ?? null;
        } catch (PDOException $exception) {
            report($exception);

            return null;
        }
    }

    public function showSystem(string $system, Request $request, StudentTeacherRatio $ratios, GadWarehouseAnalytics $gad)
    {
        $systemDefinition = $this->systemData->definition($system);

        abort_if($systemDefinition === null, 404);

        $canonicalSlug = Str::slug($systemDefinition['name']);

        if ($system !== $canonicalSlug) {
            return redirect()->route('qams.system', ['system' => $canonicalSlug]);
        }

        $isHrmis = $systemDefinition['key'] === 'human-resources';
        $isGad = $systemDefinition['key'] === 'system-13';
        $gadAnalytics = null;
        if ($isGad) {
            $month = $request->integer('month');
            try {
                $gadAnalytics = $gad->get([
                    'campus' => $request->integer('campus') ?: null,
                    'department' => $request->integer('department') ?: null,
                    'year' => $request->integer('year') ?: null,
                    'month' => $month >= 1 && $month <= 12 ? $month : null,
                ]);
            } catch (PDOException $exception) {
                report($exception);
            }
        }

        $hrmisAnalytics = null;
        $studentTeacherRatio = null;
        if ($isHrmis) {
            try {
                $hrmisAnalytics = $this->workforce->get(
                    $request->integer('campus') ?: null,
                    $request->integer('year') ?: null
                );
            } catch (QueryException $exception) {
                report($exception);
            }
        }

        if ($hrmisAnalytics !== null) {
            try {
                $studentTeacherRatio = $ratios->get($hrmisAnalytics['snapshot_id'], $hrmisAnalytics['year']);
            } catch (QueryException $exception) {
                report($exception);
            }
        }

        $systemData = $systemDefinition['key'] === 'system-17' || $isHrmis || $isGad
            ? $systemDefinition
            : $this->systemData->find($system);

        return view('personal.system-metrics', [
            'system' => $systemData,
            'cesAnalytics' => $systemDefinition['key'] === 'system-17' ? config('qams.ces_analytics') : null,
            'isHrmis' => $isHrmis,
            'hrmisAnalytics' => $hrmisAnalytics,
            'studentTeacherRatio' => $studentTeacherRatio,
            'isGad' => $isGad,
            'gadAnalytics' => $gadAnalytics,
        ]);
    }
}
