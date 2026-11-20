<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'qams_warehouse';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->create('gad_warehouse_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('completed_at');
            $table->unsignedInteger('proposal_count');
            $table->unsignedInteger('beneficiary_row_count');
            $table->unsignedInteger('accomplishment_count');
            $table->unsignedInteger('employee_login_row_count');
        });
        foreach (['campus', 'department', 'beneficiary'] as $dimension) {
            $schema->create("dim_gad_{$dimension}", function (Blueprint $table) use ($dimension) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('name');
                if ($dimension === 'department') {
                    $table->foreignId('campus_id')->nullable()->constrained('dim_gad_campus');
                }
            });
        }
        $schema->create('dim_gad_proposal', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('reference', 100);
            $table->text('title');
            $table->text('location')->nullable();
        });
        $schema->create('dim_gad_employee', function (Blueprint $table) {
            $table->string('id', 100)->primary();
        });
        $schema->create('fact_gad_proposal', function (Blueprint $table) {
            $table->foreignId('proposal_id')->primary()->constrained('dim_gad_proposal');
            $table->foreignId('campus_id')->nullable()->constrained('dim_gad_campus');
            $table->foreignId('department_id')->nullable()->constrained('dim_gad_department');
            $table->date('proposed_on')->nullable();
            $table->dateTime('submitted_at')->nullable()->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->json('schedule_windows')->nullable();
            $table->string('status', 64);
            $table->boolean('is_submitted');
            $table->decimal('budget', 15, 2)->nullable();
            $table->decimal('attribution', 15, 2)->nullable();
        });
        $schema->create('fact_gad_proposal_beneficiary', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('proposal_id')->constrained('dim_gad_proposal');
            $table->foreignId('beneficiary_id')->nullable()->constrained('dim_gad_beneficiary');
            $table->unsignedBigInteger('beneficiary_count')->nullable();
        });
        $schema->create('fact_gad_accomplishment', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('proposal_id')->constrained('dim_gad_proposal');
            $table->string('status', 64);
            $table->string('review_status', 64)->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->date('ended_on')->nullable();
            $table->text('location')->nullable();
            $table->unsignedBigInteger('participants')->nullable();
            $table->decimal('approved_budget', 15, 2)->nullable();
            $table->decimal('actual_budget_use', 15, 2)->nullable();
            $table->decimal('attribution', 15, 2)->nullable();
        });
        $schema->create('fact_gad_employee_login', function (Blueprint $table) {
            $table->string('employee_id', 100);
            $table->foreign('employee_id')->references('id')->on('dim_gad_employee');
            $table->date('login_date');
            $table->unsignedInteger('login_count');
            $table->primary(['employee_id', 'login_date']);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        foreach ([
            'fact_gad_employee_login', 'fact_gad_accomplishment', 'fact_gad_proposal_beneficiary',
            'fact_gad_proposal', 'dim_gad_employee', 'dim_gad_proposal', 'dim_gad_beneficiary',
            'dim_gad_department', 'dim_gad_campus', 'gad_warehouse_sync_runs',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
