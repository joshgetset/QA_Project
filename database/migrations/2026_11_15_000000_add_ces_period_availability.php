<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'qams_warehouse';

    public function up(): void
    {
        Schema::connection($this->connection)->table('dim_campus', function (Blueprint $table) {
            $table->boolean('income_available')->default(false)->after('source_database');
        });

        Schema::connection($this->connection)->create('ces_campus_academic_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('dim_campus')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('dim_academic_period')->cascadeOnDelete();
            $table->boolean('has_grade_table')->default(false);
            $table->boolean('has_stage_table')->default(false);
            $table->boolean('has_graduation_period')->default(false);
            $table->timestamps();
            $table->unique(['campus_id', 'academic_period_id'], 'ces_campus_period_unique');
            $table->index(['academic_period_id', 'has_grade_table'], 'ces_period_grade_idx');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ces_campus_academic_periods');
        Schema::connection($this->connection)->table('dim_campus', function (Blueprint $table) {
            $table->dropColumn('income_available');
        });
    }
};
