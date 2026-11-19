<?php

use App\Services\HrmisEmployeeClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'qams_warehouse';

    public function up(): void
    {
        $warehouse = DB::connection($this->getConnection());
        $classifier = app(HrmisEmployeeClassifier::class);

        $warehouse->transaction(function () use ($warehouse, $classifier) {
            $warehouse->table('fact_hrmis_employee_snapshot')
                ->where('is_non_teaching_full_time', true)
                ->orWhere('is_non_teaching_part_time', true)
                ->update(['is_non_teaching_full_time' => false, 'is_non_teaching_part_time' => false]);

            $warehouse->table('fact_hrmis_employee_snapshot as fact')
                ->join('dim_hrmis_employment_status as status', 'status.id', '=', 'fact.employment_status_id')
                ->leftJoin('dim_hrmis_position as position', 'position.id', '=', 'fact.position_id')
                ->select('fact.id', 'status.source_status', 'position.position_title')
                ->chunkById(1000, function ($facts) use ($warehouse, $classifier) {
                    $groups = [];
                    foreach ($facts as $fact) {
                        $position = $classifier->position($fact->position_title, $fact->source_status);
                        $status = $classifier->employment($fact->source_status);
                        $nonTeaching = $position['is_non_teaching'];
                        $flags = [
                            'is_academic_staff' => $position['is_teaching'],
                            'is_teaching_personnel' => $position['is_teaching'],
                            'is_senior_academic_staff' => $position['is_senior_academic'],
                            'is_part_time_teaching' => $position['is_part_time_teaching'],
                            'is_non_teaching' => $nonTeaching,
                            'is_non_teaching_permanent' => $nonTeaching && $status['is_permanent'],
                            'is_non_teaching_casual' => $nonTeaching && $status['is_casual'],
                            'is_non_teaching_contractual' => $nonTeaching && $status['is_contractual'],
                            'is_non_teaching_job_order' => $nonTeaching && $status['is_job_order'],
                            'is_guidance_counselor' => $nonTeaching && $position['is_guidance_counselor'],
                            'is_physical_health' => $nonTeaching && $position['is_physical_health'],
                        ];
                        $group = json_encode($flags, JSON_THROW_ON_ERROR);
                        $groups[$group][] = $fact->id;
                    }

                    foreach ($groups as $flags => $ids) {
                        $warehouse->table('fact_hrmis_employee_snapshot')->whereIn('id', $ids)
                            ->update(json_decode($flags, true, flags: JSON_THROW_ON_ERROR));
                    }
                }, 'fact.id', 'id');
        });
    }
};
