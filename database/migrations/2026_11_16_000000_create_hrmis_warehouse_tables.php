<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'qams_warehouse';

    public function up(): void
    {
        Schema::connection($this->connection)->create('hrmis_warehouse_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20);
            $table->date('snapshot_date');
            $table->unsignedInteger('employees_staged')->default(0);
            $table->unsignedInteger('education_records_staged')->default(0);
            $table->unsignedInteger('employees_loaded')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('dim_hrmis_source_campus', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('source_campus_id')->unique();
            $table->string('source_campus_label', 100);
            $table->foreignId('qams_campus_id')->nullable()->constrained('dim_campus');
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('dim_hrmis_employee', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('source_employee_id')->unique();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('dim_hrmis_department', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('source_department_id')->unique();
            $table->foreignId('campus_id')->nullable()->constrained('dim_hrmis_source_campus');
            $table->string('department_name', 200)->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('dim_hrmis_position', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('source_position_id')->unique();
            $table->foreignId('campus_id')->nullable()->constrained('dim_hrmis_source_campus');
            $table->string('position_title', 100)->nullable();
            $table->string('position_category', 40)->default('other');
            $table->boolean('is_teaching')->default(false);
            $table->boolean('is_senior_academic')->default(false);
            $table->boolean('is_guidance_counselor')->default(false);
            $table->boolean('is_physical_health')->default(false);
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('dim_hrmis_employment_status', function (Blueprint $table) {
            $table->id();
            $table->string('source_status', 100)->unique();
            $table->string('canonical_status', 40);
            $table->boolean('is_part_time')->default(false);
            $table->boolean('is_permanent')->default(false);
            $table->boolean('is_casual')->default(false);
            $table->boolean('is_contractual')->default(false);
            $table->boolean('is_job_order')->default(false);
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('dim_hrmis_gender', function (Blueprint $table) {
            $table->id();
            $table->string('gender_code', 20)->unique();
            $table->string('gender_label', 40);
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('dim_hrmis_education', function (Blueprint $table) {
            $table->id();
            $table->string('achievement_code', 40)->unique();
            $table->string('achievement_label', 80);
            $table->unsignedTinyInteger('achievement_rank');
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('hrmis_stg_employees', function (Blueprint $table) {
            $table->unsignedInteger('source_employee_id')->primary();
            $table->unsignedInteger('source_campus_id')->default(0);
            $table->unsignedInteger('source_department_id')->nullable();
            $table->unsignedInteger('source_position_id')->nullable();
            $table->string('source_position_title', 100)->nullable();
            $table->string('source_employment_status', 100)->nullable();
            $table->string('gender_code', 20)->default('unknown');
            $table->boolean('is_pwd')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('loaded_at');
        });

        Schema::connection($this->connection)->create('hrmis_stg_departments', function (Blueprint $table) {
            $table->unsignedInteger('source_department_id')->primary();
            $table->unsignedInteger('source_campus_id')->default(0);
            $table->string('department_name', 200)->nullable();
            $table->timestamp('loaded_at');
        });

        Schema::connection($this->connection)->create('hrmis_stg_positions', function (Blueprint $table) {
            $table->unsignedInteger('source_position_id')->primary();
            $table->unsignedInteger('source_campus_id')->default(0);
            $table->string('position_title', 100)->nullable();
            $table->timestamp('loaded_at');
        });

        Schema::connection($this->connection)->create('hrmis_stg_education_records', function (Blueprint $table) {
            $table->unsignedInteger('source_education_id')->primary();
            $table->unsignedInteger('source_employee_id')->index();
            $table->string('student_level', 200)->nullable();
            $table->string('degree_course', 300)->nullable();
            $table->string('units_earned', 100)->nullable();
            $table->string('year_graduated', 100)->nullable();
            $table->unsignedInteger('source_order')->nullable();
            $table->timestamp('loaded_at');
        });

        Schema::connection($this->connection)->create('fact_hrmis_employee_snapshot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_run_id')->constrained('hrmis_warehouse_sync_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('dim_hrmis_employee');
            $table->foreignId('campus_id')->constrained('dim_hrmis_source_campus');
            $table->foreignId('department_id')->nullable()->constrained('dim_hrmis_department');
            $table->foreignId('position_id')->nullable()->constrained('dim_hrmis_position');
            $table->foreignId('employment_status_id')->constrained('dim_hrmis_employment_status');
            $table->foreignId('gender_id')->constrained('dim_hrmis_gender');
            $table->foreignId('education_id')->constrained('dim_hrmis_education');
            $table->date('snapshot_date');
            $table->unsignedTinyInteger('employee_count')->default(1);
            $table->boolean('is_academic_staff')->default(false);
            $table->boolean('is_senior_academic_staff')->default(false);
            $table->boolean('is_teaching_personnel')->default(false);
            $table->boolean('is_part_time_teaching')->default(false);
            $table->boolean('is_non_teaching')->default(false);
            $table->boolean('is_non_teaching_full_time')->default(false);
            $table->boolean('is_non_teaching_part_time')->default(false);
            $table->boolean('is_non_teaching_permanent')->default(false);
            $table->boolean('is_non_teaching_casual')->default(false);
            $table->boolean('is_non_teaching_contractual')->default(false);
            $table->boolean('is_non_teaching_job_order')->default(false);
            $table->boolean('is_guidance_counselor')->default(false);
            $table->boolean('is_physical_health')->default(false);
            $table->boolean('is_pwd')->nullable();
            $table->timestamps();
            $table->unique(['sync_run_id', 'employee_id'], 'hrmis_snapshot_employee_unique');
            $table->index(['sync_run_id', 'campus_id']);
            $table->index(['sync_run_id', 'is_academic_staff', 'gender_id'], 'hrmis_academic_gender_idx');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('fact_hrmis_employee_snapshot');
        Schema::connection($this->connection)->dropIfExists('hrmis_stg_education_records');
        Schema::connection($this->connection)->dropIfExists('hrmis_stg_positions');
        Schema::connection($this->connection)->dropIfExists('hrmis_stg_departments');
        Schema::connection($this->connection)->dropIfExists('hrmis_stg_employees');
        Schema::connection($this->connection)->dropIfExists('dim_hrmis_education');
        Schema::connection($this->connection)->dropIfExists('dim_hrmis_gender');
        Schema::connection($this->connection)->dropIfExists('dim_hrmis_employment_status');
        Schema::connection($this->connection)->dropIfExists('dim_hrmis_position');
        Schema::connection($this->connection)->dropIfExists('dim_hrmis_department');
        Schema::connection($this->connection)->dropIfExists('dim_hrmis_employee');
        Schema::connection($this->connection)->dropIfExists('dim_hrmis_source_campus');
        Schema::connection($this->connection)->dropIfExists('hrmis_warehouse_sync_runs');
    }
};
