<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GadWarehouseAnalytics
{
    private const STATUSES = ['submitted', 'approved', 'ongoing', 'overdue', 'completed'];
    private const RESPONSIVE_SCORE_RANGE = [15, 20];

    public function overview(): ?array
    {
        $warehouse = DB::connection('qams_warehouse');

        return $warehouse->transaction(function () use ($warehouse): ?array {
            $updatedAt = $this->lastPublishedAt($warehouse);
            if ($updatedAt === null) {
                return null;
            }

            $total = $warehouse->table('fact_gad_proposal')->where('is_submitted', true)
                ->whereBetween('hgdg_score', self::RESPONSIVE_SCORE_RANGE)->count();

            return [
                'source' => 'cached',
                'source_label' => 'Warehouse snapshot',
                'summary' => 'Submitted proposals with an HGDG score of 15 to 20.',
                'updated_at' => $updatedAt,
                'metrics' => [['label' => 'Total Gender Responsive Proposals', 'value' => number_format($total)]],
            ];
        });
    }

    public function get(array $filters): ?array
    {
        $warehouse = DB::connection('qams_warehouse');

        return $warehouse->transaction(function () use ($warehouse, $filters): ?array {
            $updatedAt = $this->lastPublishedAt($warehouse);
            if ($updatedAt === null) {
                return null;
            }

            $base = $warehouse->table('fact_gad_proposal as f')->where('f.is_submitted', true);
            $years = (clone $base)->whereNotNull('f.submitted_at')
                ->selectRaw('DISTINCT SUBSTR(f.submitted_at, 1, 4) as submission_year')
                ->orderByDesc('submission_year')->pluck('submission_year')->map(fn ($year) => (int) $year);
            $year = $years->containsStrict($filters['year']) ? $filters['year'] : ($years->first() ?? now('Asia/Manila')->year);
            $month = $filters['month'];
            $campuses = $warehouse->table('dim_gad_campus')->orderBy('id')->pluck('name', 'id');
            $campusId = $campuses->has($filters['campus']) ? $filters['campus'] : null;
            $departments = $campusId === null ? collect() : (clone $base)
                ->join('dim_gad_department as d', 'd.id', '=', 'f.department_id')
                ->where('f.campus_id', $campusId)->select('d.id', 'd.name')->distinct()->orderBy('d.name')->pluck('d.name', 'd.id');
            $departmentId = $departments->has($filters['department']) ? $filters['department'] : null;
            $periodStart = sprintf('%04d-%02d-01', $year, $month ?? 1);
            $periodEnd = ($month === null
                ? Carbon::parse($periodStart)->addYear()
                : Carbon::parse($periodStart)->addMonth())->toDateString();
            $cohort = $base->where('f.submitted_at', '>=', $periodStart)->where('f.submitted_at', '<', $periodEnd)
                ->when($campusId !== null, fn ($query) => $query->where('f.campus_id', $campusId))
                ->when($departmentId !== null, fn ($query) => $query->where('f.department_id', $departmentId));

            $beneficiaries = $warehouse->table('fact_gad_proposal_beneficiary')
                ->selectRaw('proposal_id, SUM(beneficiary_count) AS beneficiary_total')->groupBy('proposal_id');
            $reports = $warehouse->table('fact_gad_accomplishment')
                ->selectRaw("proposal_id, MAX(CASE WHEN review_status = 'approved' THEN 1 ELSE 0 END) AS completed")
                ->groupBy('proposal_id');
            $proposals = (clone $cohort)->join('dim_gad_proposal as p', 'p.id', '=', 'f.proposal_id')
                ->leftJoin('dim_gad_department as d', 'd.id', '=', 'f.department_id')
                ->leftJoinSub($beneficiaries, 'b', 'b.proposal_id', '=', 'f.proposal_id')
                ->leftJoinSub($reports, 'a', 'a.proposal_id', '=', 'f.proposal_id')
                ->orderByDesc('f.submitted_at')->orderByDesc('f.proposal_id')->get([
                    'f.proposal_id', 'f.campus_id', 'f.submitted_at', 'f.proposed_on', 'f.budget', 'f.attribution',
                    'f.ends_on', 'f.schedule_windows', 'f.status', 'p.reference', 'p.title', 'p.location',
                    'd.name as department', 'b.beneficiary_total', 'a.completed', 'f.hgdg_score',
                ]);

            $empty = array_fill_keys(self::STATUSES, 0);
            $summary = $empty;
            $months = array_fill(1, 12, $empty);
            $byCampus = array_fill_keys($campusId === null ? $campuses->keys()->all() : [$campusId], $empty);
            $today = now('Asia/Manila')->toDateString();
            foreach ($proposals as $proposal) {
                $proposal->outcome = $this->outcome($proposal, $today);
                $submissionMonth = (int) substr($proposal->submitted_at, 5, 2);
                $campus = $proposal->campus_id ?? 'unknown';
                $byCampus[$campus] ??= $empty;
                $statuses = ['submitted'];
                if ($proposal->status === 'approved') {
                    $statuses[] = 'approved';
                    if ($proposal->outcome !== 'approved') {
                        $statuses[] = $proposal->outcome;
                    }
                }
                foreach ($statuses as $status) {
                    $summary[$status]++;
                    $months[$submissionMonth][$status]++;
                    $byCampus[$campus][$status]++;
                }
            }

            $accomplishmentQuery = $warehouse->table('fact_gad_accomplishment as a')
                ->joinSub((clone $cohort)->select('f.proposal_id'), 'cohort', 'cohort.proposal_id', '=', 'a.proposal_id');
            $accomplishmentTotal = (clone $accomplishmentQuery)->count();
            $accomplishments = $accomplishmentQuery->where('a.review_status', 'approved')
                ->join('dim_gad_proposal as p', 'p.id', '=', 'a.proposal_id')
                ->orderByDesc('a.submitted_at')->orderByDesc('a.id')
                ->paginate(10, ['a.*', 'p.reference', 'p.title'], 'accomplishment_page')->withQueryString();
            $proposalPage = LengthAwarePaginator::resolveCurrentPage('proposal_page');
            $proposalsPerPage = 10;
            $proposalRows = new LengthAwarePaginator(
                $proposals->forPage($proposalPage, $proposalsPerPage), $proposals->count(), $proposalsPerPage, $proposalPage,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'proposal_page']
            );

            return [
                'updatedAt' => $updatedAt, 'years' => $years, 'year' => $year, 'month' => $month,
                'campuses' => $campuses, 'campusId' => $campusId, 'departments' => $departments, 'departmentId' => $departmentId,
                'summary' => $summary, 'months' => $months, 'byCampus' => $byCampus,
                'proposalRows' => $proposalRows->withQueryString(),
                'beneficiaryTotal' => $proposals->whereNotNull('beneficiary_total')->isEmpty() ? null : $proposals->sum('beneficiary_total'),
                'accomplishments' => $accomplishments,
                'accomplishmentTotal' => $accomplishmentTotal,
                'genderResponsiveTotal' => $proposals->whereBetween('hgdg_score', self::RESPONSIVE_SCORE_RANGE)->count(),
            ];
        });
    }

    private function lastPublishedAt(Connection $warehouse): ?string
    {
        if (! $warehouse->getSchemaBuilder()->hasTable('gad_warehouse_sync_runs')) {
            return null;
        }

        return $warehouse->table('gad_warehouse_sync_runs')->orderByDesc('id')->value('completed_at');
    }

    private function outcome(object $proposal, string $today): string
    {
        if ($proposal->status !== 'approved') {
            return $proposal->status;
        }
        if ($proposal->completed) {
            return 'completed';
        }
        if ($proposal->ends_on !== null && $proposal->ends_on < $today) {
            return 'overdue';
        }
        foreach (json_decode($proposal->schedule_windows ?? '[]', true, flags: JSON_THROW_ON_ERROR) as $window) {
            if ($window['start'] <= $today && $today <= $window['end']) {
                return 'ongoing';
            }
        }

        return 'approved';
    }
}
