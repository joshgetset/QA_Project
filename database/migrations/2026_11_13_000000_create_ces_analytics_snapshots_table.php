<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'ces_reporting';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ces_analytics_snapshots', function (Blueprint $table) {
            $table->string('snapshot_key', 40)->primary();
            $table->longText('payload');
            $table->timestamp('synced_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ces_analytics_snapshots');
    }
};
