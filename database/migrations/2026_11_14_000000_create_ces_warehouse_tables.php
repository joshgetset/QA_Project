<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'qams_warehouse';

    public function up(): void
    {
        Schema::connection($this->connection)->create('dim_campus', function (Blueprint $table) {
            $table->id();
            $table->string('campus_code', 40)->unique();
            $table->string('campus_name', 100);
            $table->string('source_database', 100)->unique();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('dim_academic_period', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 4);
            $table->string('semester', 2);
            $table->timestamps();
            $table->unique(['academic_year', 'semester']);
        });

        Schema::connection($this->connection)->create('dim_ces_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('dim_campus');
            $table->string('source_program_id', 64);
            $table->string('course_title', 255)->nullable();
            $table->string('college_name', 255)->nullable();
            $table->string('department_name', 255)->nullable();
            $table->timestamps();
            $table->unique(['campus_id', 'source_program_id'], 'ces_program_source_unique');
            $table->index(['college_name', 'course_title']);
        });

        Schema::connection($this->connection)->create('dim_ces_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('dim_campus');
            $table->string('source_student_no', 100);
            $table->string('sex', 40)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('disability', 255)->nullable();
            $table->string('family_4ps_status', 100)->nullable();
            $table->boolean('is_four_ps_beneficiary')->default(false);
            $table->boolean('is_first_generation')->default(false);
            $table->decimal('annual_income', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['campus_id', 'source_student_no'], 'ces_student_source_unique');
            $table->index(['campus_id', 'sex']);
        });

        Schema::connection($this->connection)->create('dim_ces_scholarship', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('dim_campus');
            $table->string('source_scholarship_id', 64);
            $table->string('source_name', 255)->nullable();
            $table->string('canonical_name', 100)->nullable();
            $table->timestamps();
            $table->unique(['campus_id', 'source_scholarship_id'], 'ces_scholarship_source_unique');
        });

        Schema::connection($this->connection)->create('fact_ces_enrollment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('dim_campus');
            $table->foreignId('academic_period_id')->constrained('dim_academic_period');
            $table->foreignId('student_id')->constrained('dim_ces_student');
            $table->foreignId('program_id')->nullable()->constrained('dim_ces_program');
            $table->string('source_table', 64);
            $table->timestamps();
            $table->unique(['campus_id', 'academic_period_id', 'student_id'], 'ces_enrollment_source_unique');
            $table->index(['academic_period_id', 'program_id']);
        });

        Schema::connection($this->connection)->create('fact_ces_registration', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('dim_campus');
            $table->foreignId('academic_period_id')->nullable()->constrained('dim_academic_period');
            $table->foreignId('student_id')->constrained('dim_ces_student');
            $table->foreignId('program_id')->nullable()->constrained('dim_ces_program');
            $table->foreignId('scholarship_id')->nullable()->constrained('dim_ces_scholarship');
            $table->string('source_registration_key', 191);
            $table->string('source_school_year', 20)->nullable();
            $table->string('source_semester', 20)->nullable();
            $table->string('source_student_status', 100)->nullable();
            $table->string('source_scholarship_id', 64)->nullable();
            $table->boolean('is_finalized')->default(false);
            $table->string('tes_value', 40)->nullable();
            $table->string('unifast_paid_value', 40)->nullable();
            $table->timestamps();
            $table->unique(['campus_id', 'source_registration_key'], 'ces_registration_source_unique');
            $table->index(['academic_period_id', 'is_finalized']);
        });

        Schema::connection($this->connection)->create('fact_ces_graduation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('dim_campus');
            $table->foreignId('academic_period_id')->nullable()->constrained('dim_academic_period');
            $table->foreignId('student_id')->constrained('dim_ces_student');
            $table->foreignId('program_id')->nullable()->constrained('dim_ces_program');
            $table->string('source_table', 64);
            $table->string('source_record_key', 191);
            $table->boolean('is_graduation_applicant')->default(false);
            $table->timestamps();
            $table->unique(['campus_id', 'source_table', 'source_record_key'], 'ces_graduation_source_unique');
            $table->index(['academic_period_id', 'is_graduation_applicant'], 'ces_graduation_period_idx');
        });

        Schema::connection($this->connection)->create('ces_warehouse_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('ces_warehouse_campus_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ces_warehouse_sync_runs')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('dim_campus');
            $table->string('status', 20);
            $table->unsignedBigInteger('enrollments_loaded')->default(0);
            $table->unsignedBigInteger('registrations_loaded')->default(0);
            $table->unsignedBigInteger('graduations_loaded')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'campus_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ces_warehouse_campus_syncs');
        Schema::connection($this->connection)->dropIfExists('ces_warehouse_sync_runs');
        Schema::connection($this->connection)->dropIfExists('fact_ces_graduation');
        Schema::connection($this->connection)->dropIfExists('fact_ces_registration');
        Schema::connection($this->connection)->dropIfExists('fact_ces_enrollment');
        Schema::connection($this->connection)->dropIfExists('dim_ces_scholarship');
        Schema::connection($this->connection)->dropIfExists('dim_ces_student');
        Schema::connection($this->connection)->dropIfExists('dim_ces_program');
        Schema::connection($this->connection)->dropIfExists('dim_academic_period');
        Schema::connection($this->connection)->dropIfExists('dim_campus');
    }
};
