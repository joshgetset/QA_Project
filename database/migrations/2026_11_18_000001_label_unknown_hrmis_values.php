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
            $warehouse->table('dim_hrmis_gender')->whereIn('gender_code', ['other', 'unknown'])
                ->update(['gender_label' => 'Unknown Gender']);

            $statuses = $warehouse->table('dim_hrmis_employment_status');
            (clone $statuses)->whereIn('canonical_status', ['other', 'unknown'])
                ->whereRaw('LOWER(source_status) LIKE ?', ['%temporary%'])
                ->update(['canonical_status' => 'temporary']);
            (clone $statuses)->whereIn('canonical_status', ['other', 'unknown'])
                ->update(['canonical_status' => 'unknown_emp_status']);

            if (! (clone $statuses)->where('source_status', 'Unknown Emp Status')->exists()) {
                (clone $statuses)->where('source_status', 'Unknown')->update(['source_status' => 'Unknown Emp Status']);
            }
        });
    }

    public function down(): void
    {
        $warehouse = DB::connection($this->getConnection());
        $warehouse->transaction(function () use ($warehouse) {
            $warehouse->table('dim_hrmis_gender')->where('gender_code', 'other')->update(['gender_label' => 'Other']);
            $warehouse->table('dim_hrmis_gender')->where('gender_code', 'unknown')->update(['gender_label' => 'Unknown']);

            $statuses = $warehouse->table('dim_hrmis_employment_status');
            (clone $statuses)->whereIn('canonical_status', ['unknown_emp_status', 'temporary'])
                ->update(['canonical_status' => 'other']);

            if (! (clone $statuses)->where('source_status', 'Unknown')->exists()) {
                (clone $statuses)->where('source_status', 'Unknown Emp Status')->update(['source_status' => 'Unknown']);
            }
        });
    }
};
