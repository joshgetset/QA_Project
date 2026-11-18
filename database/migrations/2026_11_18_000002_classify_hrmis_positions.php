<?php

use App\Services\HrmisEmployeeClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'qams_warehouse';

    public function up(): void
    {
        $warehouse = DB::connection($this->getConnection());
        $classifier = new HrmisEmployeeClassifier;
        $warehouse->transaction(function () use ($warehouse, $classifier) {
            $warehouse->table('dim_hrmis_position')->where('position_category', 'other')
                ->chunkById(200, function ($positions) use ($warehouse, $classifier) {
                    foreach ($positions as $position) {
                        $classification = $classifier->position($position->position_title);
                        $warehouse->table('dim_hrmis_position')->where('id', $position->id)->update([
                            'position_category' => $classification['category'],
                            'is_teaching' => $classification['is_teaching'],
                        ]);
                    }
                });
        });

        Schema::connection($this->getConnection())->table('dim_hrmis_position', function (Blueprint $table) {
            $table->string('position_category', 40)->default('unknown_position')->change();
        });
    }

    public function down(): void
    {
        $warehouse = DB::connection($this->getConnection());
        $positions = $warehouse->table('dim_hrmis_position');
        (clone $positions)->where('position_category', 'teacher')->update([
            'position_category' => 'other',
        ]);
        $positions->whereIn('position_category', ['unknown_position', 'non_teaching', 'teaching_unranked'])
            ->update(['position_category' => 'other']);

        Schema::connection($this->getConnection())->table('dim_hrmis_position', function (Blueprint $table) {
            $table->string('position_category', 40)->default('other')->change();
        });
    }
};
