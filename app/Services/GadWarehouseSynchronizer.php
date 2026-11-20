<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GadWarehouseSynchronizer
{
    public function sync(): array
    {
        return Cache::lock('gad:sync-warehouse', 600)->block(0, function (): array {
            $url = config('source_apis.gad.url');
            $token = config('source_apis.gad.api_key');
            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
                || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
                || ! is_string($token) || $token === '') {
                throw new RuntimeException('Configure QAMS_GAD_SOURCE_API_URL and GAD_API_KEY before syncing.');
            }

            $response = Http::acceptJson()->withToken($token)->connectTimeout(5)->timeout(90)->get($url);
            if (! $response->successful()) {
                throw new RuntimeException('GAD source API returned HTTP '.$response->status().'.');
            }

            $tables = $this->transform($response->json());
            $warehouse = DB::connection('qams_warehouse');

            return $warehouse->transaction(function () use ($warehouse, $tables): array {
                foreach (array_reverse(array_keys($tables)) as $table) {
                    $warehouse->table($table)->delete();
                }
                foreach ($tables as $table => $rows) {
                    foreach (array_chunk($rows, 250) as $chunk) {
                        $warehouse->table($table)->insert($chunk);
                    }
                }

                $counts = [
                    'proposal_count' => count($tables['fact_gad_proposal']),
                    'beneficiary_row_count' => count($tables['fact_gad_proposal_beneficiary']),
                    'accomplishment_count' => count($tables['fact_gad_accomplishment']),
                    'employee_login_row_count' => count($tables['fact_gad_employee_login']),
                ];
                $warehouse->table('gad_warehouse_sync_runs')->insert(['completed_at' => now(), ...$counts]);

                return $counts;
            });
        });
    }

    private function transform(mixed $payload): array
    {
        if (($payload['version'] ?? null) !== 1 || empty($payload['proposals'])) {
            throw new RuntimeException('GAD source returned an unsupported or empty proposal snapshot.');
        }
        foreach (['proposals', 'beneficiaries', 'accomplishments', 'employee_logins'] as $dataset) {
            if (! is_array($payload[$dataset] ?? null) || ! array_is_list($payload[$dataset])) {
                throw new RuntimeException("GAD source response is missing a valid {$dataset} dataset.");
            }
        }

        $campuses = $departments = $proposals = $types = $employees = [];
        $proposalFacts = $beneficiaryFacts = $accomplishmentFacts = $loginFacts = [];
        foreach ($payload['proposals'] as $row) {
            $id = (int) $row['id'];
            if ($id < 1 || isset($proposals[$id]) || trim((string) $row['reference']) === '') {
                throw new RuntimeException('GAD source returned a missing or duplicate proposal identifier.');
            }
            if (! array_key_exists('hgdg_score', $row) || ($row['hgdg_score'] !== null && ! is_numeric($row['hgdg_score']))) {
                throw new RuntimeException('GAD source returned a missing or invalid HGDG score field.');
            }
            $campusId = (int) $row['campus_id'] ?: null;
            $departmentId = (int) $row['department_id'] ?: null;
            if ($campusId !== null) {
                $campuses[$campusId] = ['id' => $campusId, 'name' => config("gad.campuses.{$campusId}", "Campus {$campusId}")];
            }
            if ($departmentId !== null) {
                $departments[$departmentId] ??= [
                    'id' => $departmentId, 'campus_id' => $campusId,
                    'name' => $row['department_lookup'] ?: ($row['department_name'] ?: "Department {$departmentId}"),
                ];
            }
            $proposals[$id] = [
                'id' => $id, 'reference' => trim($row['reference']),
                'title' => (string) $row['title'], 'location' => $row['location'],
            ];
            $windows = $this->scheduleWindows($row['schedules'], (string) $row['schedule_type']);
            $status = strtolower(trim((string) $row['status']));
            $proposalFacts[] = [
                'proposal_id' => $id, 'campus_id' => $campusId, 'department_id' => $departmentId,
                'proposed_on' => $row['proposed_on'], 'submitted_at' => $row['submitted_at'],
                'starts_on' => $windows === [] ? null : min(array_column($windows, 'start')),
                'ends_on' => $windows === [] ? null : max(array_column($windows, 'end')),
                'schedule_windows' => $windows === [] ? null : json_encode($windows, JSON_THROW_ON_ERROR),
                'status' => $status,
                'is_submitted' => in_array($status, ['approved', 'pending', 'returned', 'resubmitted', 'submitted', 'rejected'], true)
                    && ($row['progress_status'] === 'completed' || in_array($status, ['approved', 'returned', 'resubmitted', 'rejected'], true)),
                'budget' => $row['budget'], 'attribution' => $row['attribution'],
                'hgdg_score' => $row['hgdg_score'],
            ];
        }
        foreach ($payload['beneficiaries'] as $row) {
            $typeId = (int) $row['beneficiary_id'] ?: null;
            if ($typeId !== null) {
                $types[$typeId] ??= ['id' => $typeId, 'name' => $row['name'] ?: "Beneficiary {$typeId}"];
            }
            $beneficiaryFacts[] = [
                'id' => $row['id'], 'proposal_id' => $row['proposal_id'], 'beneficiary_id' => $typeId,
                'beneficiary_count' => $row['total'],
            ];
        }
        foreach ($payload['accomplishments'] as $row) {
            $accomplishmentFacts[] = [
                'id' => $row['id'], 'proposal_id' => $row['proposal_id'], 'status' => $row['status'],
                'review_status' => $row['review_status'], 'submitted_at' => $row['submitted_at'],
                'ended_on' => $row['ended_on'], 'location' => $row['location'], 'participants' => $row['participants'],
                'approved_budget' => $row['approved_budget'], 'actual_budget_use' => $row['actual_budget_use'],
                'attribution' => $row['attribution'],
            ];
        }
        foreach ($payload['employee_logins'] as $row) {
            $id = (string) $row['employee_id'];
            $employees[$id] = ['id' => $id];
            $loginFacts[] = ['employee_id' => $id, 'login_date' => $row['login_date'], 'login_count' => $row['login_count']];
        }

        return [
            'dim_gad_campus' => array_values($campuses),
            'dim_gad_department' => array_values($departments),
            'dim_gad_proposal' => array_values($proposals),
            'dim_gad_beneficiary' => array_values($types),
            'dim_gad_employee' => array_values($employees),
            'fact_gad_proposal' => $proposalFacts,
            'fact_gad_proposal_beneficiary' => $beneficiaryFacts,
            'fact_gad_accomplishment' => $accomplishmentFacts,
            'fact_gad_employee_login' => $loginFacts,
        ];
    }

    private function scheduleWindows(?string $json, string $type): array
    {
        $schedule = $json === null ? [] : json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $periods = $type === 'multiple' ? ($schedule[$type] ?? []) : [$schedule[$type] ?? []];
        $windows = [];
        foreach ($periods as $period) {
            $start = $period['start_date'] ?? '';
            $end = $period['end_date'] ?? '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $end) && $start <= $end) {
                $windows[] = ['start' => $start, 'end' => $end];
            }
        }

        return $windows;
    }
}
