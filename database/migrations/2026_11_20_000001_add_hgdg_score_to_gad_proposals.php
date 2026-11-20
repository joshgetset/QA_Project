<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'qams_warehouse';

    public function up(): void
    {
        Schema::connection($this->connection)->table('fact_gad_proposal', function (Blueprint $table) {
            $table->decimal('hgdg_score', 8, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('fact_gad_proposal', function (Blueprint $table) {
            $table->dropColumn('hgdg_score');
        });
    }
};
