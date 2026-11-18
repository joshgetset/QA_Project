<?php

use App\Services\HrmisCampusDirectory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'qams_warehouse';

    public function up(): void
    {
        $warehouse = DB::connection($this->getConnection());
        $warehouse->transaction(fn () => app(HrmisCampusDirectory::class)->synchronize(
            $warehouse, array_keys(config('hrmis.campus_codes')), now()
        ));
    }

    public function down(): void
    {
        $warehouse = DB::connection($this->getConnection());
        foreach (array_keys(config('hrmis.campus_codes')) as $sourceId) {
            $warehouse->table('dim_hrmis_source_campus')->where('source_campus_id', $sourceId)->update([
                'source_campus_label' => "HRMIS campus ID $sourceId",
                'qams_campus_id' => null,
                'updated_at' => now(),
            ]);
        }
    }
};
