<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'qams_warehouse';

    public function up(): void
    {
        $warehouse = DB::connection($this->getConnection());
        $warehouse->transaction(function () use ($warehouse) {
            $statuses = $warehouse->table('dim_hrmis_employment_status');
            $partTimers = (clone $statuses)->whereRaw('LOWER(TRIM(source_status)) IN (?, ?)', ['part timer', 'part-timer']);
            $jobOrders = (clone $statuses)->whereRaw('LOWER(TRIM(source_status)) = ?', ['job']);
            $unclassified = $warehouse->table('fact_hrmis_employee_snapshot')
                ->where('is_teaching_personnel', false)
                ->where('is_non_teaching', false)
                ->whereNull('position_id');

            (clone $unclassified)->whereIn('employment_status_id', $partTimers->select('id'))->update([
                'is_academic_staff' => true,
                'is_teaching_personnel' => true,
                'is_part_time_teaching' => true,
            ]);
            (clone $unclassified)->whereIn('employment_status_id', (clone $jobOrders)->select('id'))->update([
                'is_non_teaching' => true,
                'is_non_teaching_full_time' => true,
                'is_non_teaching_job_order' => true,
            ]);
            $jobOrders->update(['canonical_status' => 'job_order', 'is_job_order' => true]);
        });
    }

    public function down(): void
    {
        $warehouse = DB::connection($this->getConnection());
        $warehouse->transaction(function () use ($warehouse) {
            $statuses = $warehouse->table('dim_hrmis_employment_status');
            $partTimers = (clone $statuses)->whereRaw('LOWER(TRIM(source_status)) IN (?, ?)', ['part timer', 'part-timer']);
            $jobOrders = (clone $statuses)->whereRaw('LOWER(TRIM(source_status)) = ?', ['job']);
            $withoutPosition = $warehouse->table('fact_hrmis_employee_snapshot')->whereNull('position_id');

            (clone $withoutPosition)->whereIn('employment_status_id', $partTimers->select('id'))->update([
                'is_academic_staff' => false,
                'is_teaching_personnel' => false,
                'is_part_time_teaching' => false,
            ]);
            (clone $withoutPosition)->whereIn('employment_status_id', (clone $jobOrders)->select('id'))->update([
                'is_non_teaching' => false,
                'is_non_teaching_full_time' => false,
                'is_non_teaching_job_order' => false,
            ]);
            $jobOrders->update(['canonical_status' => 'unknown_emp_status', 'is_job_order' => false]);
        });
    }
};
