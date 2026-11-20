<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class GadSourceExport
{
    public function snapshot(): array
    {
        $database = config('gad.source_database');
        if (! is_string($database) || ! preg_match('/^[A-Za-z0-9_]+$/D', $database)) {
            throw new RuntimeException('Invalid GAD source database name.');
        }

        $source = DB::connection(config('gad.source_connection'));

        return $source->transaction(function () use ($source, $database): array {
            $proposals = $source->table("$database.proposal as p")->whereNull('p.deleted_at');
            $scores = $source->table("$database.hgdg_proposal_evaluation")
                ->whereNull('deleted_at')
                ->selectRaw('proposal_id, total_score, ROW_NUMBER() OVER (PARTITION BY proposal_id ORDER BY COALESCE(updated_at, created_at) DESC, evaluation_ID DESC) AS score_rank');

            return [
                'version' => 1,
                'proposals' => (clone $proposals)
                    ->leftJoin("$database.departments as d", 'd.id', '=', 'p.department_id')
                    ->leftJoinSub($scores, 'hgdg', fn ($join) => $join
                        ->on('hgdg.proposal_id', '=', 'p.proposal_id')
                        ->where('hgdg.score_rank', 1))
                    ->orderBy('p.proposal_id')
                    ->get([
                        'p.proposal_id as id', 'p.tracking_no as reference', 'p.title', 'p.date as proposed_on',
                        'p.created_at as submitted_at', 'p.budget', 'p.location', 'p.gad_attribution as attribution',
                        'p.department_id', 'p.department_name', 'd.name as department_lookup', 'p.campus_id',
                        'p.status', 'p.progress_status', 'p.schedule_type', 'p.schedules', 'hgdg.total_score as hgdg_score',
                    ])->all(),
                'beneficiaries' => $source->table("$database.proposal_beneficiaries as b")
                    ->joinSub((clone $proposals)->select('p.proposal_id'), 'p', 'b.proposal_id', '=', 'p.proposal_id')
                    ->leftJoin("$database.beneficiary as t", 'b.beneficiary_id', '=', 't.beneficiary_id')
                    ->whereNull('b.deleted_at')
                    ->orderBy('b.prop_bene_id')
                    ->get(['b.prop_bene_id as id', 'b.proposal_id', 'b.beneficiary_id', 't.beneficiary as name', 'b.total_beneficiaries as total'])
                    ->all(),
                'accomplishments' => $source->table("$database.accomplishment_reports as a")
                    ->joinSub((clone $proposals)->select('p.proposal_id'), 'p', 'a.proposal_id', '=', 'p.proposal_id')
                    ->orderBy('a.id')
                    ->get([
                        'a.id', 'a.proposal_id', 'a.status', 'a.gad_review_status as review_status', 'a.submitted_at',
                        'a.actual_date_ended as ended_on', 'a.actual_location as location', 'a.total_participants as participants',
                        'a.approved_budget', 'a.actual_budget_use', 'a.gad_ar_attribution_amount as attribution',
                    ])->all(),
                'employee_logins' => $source->table("$database.activity_log")
                    ->where('event', 'privileged_login')
                    ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.employee_id')) AS employee_id, DATE(created_at) AS login_date, COUNT(*) AS login_count")
                    ->groupBy('employee_id', 'login_date')
                    ->havingRaw("employee_id IS NOT NULL AND employee_id NOT IN ('', 'null') AND login_date IS NOT NULL")
                    ->orderBy('login_date')->orderBy('employee_id')
                    ->get()->all(),
            ];
        });
    }
}
