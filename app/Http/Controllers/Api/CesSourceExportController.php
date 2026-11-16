<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CesSourceExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CesSourceExportController extends Controller
{
    public function manifest(string $campus, CesSourceExportService $exporter): JsonResponse
    {
        return response()->json(['data' => $exporter->manifest($campus)]);
    }

    public function scholarships(string $campus, CesSourceExportService $exporter): JsonResponse
    {
        return response()->json(['data' => $exporter->scholarships($campus)]);
    }

    public function programs(Request $request, string $campus, CesSourceExportService $exporter): JsonResponse
    {
        $validated = $request->validate([
            'course_ids' => ['present', 'array', 'max:1000'],
            'course_ids.*' => ['required', 'string', 'max:191'],
        ]);

        return response()->json([
            'data' => $exporter->programs($campus, array_values(array_unique($validated['course_ids']))),
        ]);
    }

    public function students(Request $request, string $campus, CesSourceExportService $exporter): JsonResponse
    {
        $validated = $request->validate([
            'student_numbers' => ['present', 'array', 'max:1000'],
            'student_numbers.*' => ['required', 'string', 'max:191'],
        ]);

        return response()->json([
            'data' => $exporter->students($campus, array_values(array_unique($validated['student_numbers']))),
        ]);
    }

    public function studentNumbers(
        Request $request,
        string $campus,
        string $dataset,
        CesSourceExportService $exporter
    ): JsonResponse {
        abort_unless(in_array($dataset, ['enrollments', 'graduations'], true), 404);
        $validated = $this->pagination($request);
        $validated['table'] = $request->validate([
            'table' => ['required', 'string', 'regex:/^(grades|stage)\d{4}[129]$/'],
        ])['table'];
        $result = $exporter->studentNumbers(
            $campus,
            $dataset,
            $validated['table'],
            $validated['page'],
            $validated['per_page']
        );

        return $this->pageResponse($result['data'], $result['has_more'], $validated['page'], $validated['per_page']);
    }

    public function registrations(Request $request, string $campus, CesSourceExportService $exporter): JsonResponse
    {
        $pagination = $this->pagination($request);
        $result = $exporter->registrations($campus, $pagination['page'], $pagination['per_page']);

        return $this->pageResponse($result['data'], $result['has_more'], $pagination['page'], $pagination['per_page']);
    }

    public function graduationList(Request $request, string $campus, CesSourceExportService $exporter): JsonResponse
    {
        $pagination = $this->pagination($request);
        $result = $exporter->graduationList($campus, $pagination['page'], $pagination['per_page']);

        return $this->pageResponse($result['data'], $result['has_more'], $pagination['page'], $pagination['per_page']);
    }

    /**
     * @return array{page: int, per_page: int}
     */
    private function pagination(Request $request): array
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        return [
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => max(1, min(1000, (int) ($validated['per_page'] ?? config('source_apis.ces.page_size', 1000)))),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $data
     */
    private function pageResponse(array $data, bool $hasMore, int $page, int $perPage): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'has_more' => $hasMore,
            ],
        ]);
    }
}
