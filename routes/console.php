<?php

use App\Services\CesWarehouseSynchronizer;
use App\Services\HrmisWarehouseSynchronizer;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('qams:warehouse:create', function () {
    $database = config('database.connections.qams_warehouse.database');
    if (! is_string($database) || ! preg_match('/^[A-Za-z0-9_]+$/D', $database)) {
        throw new RuntimeException('QAMS warehouse database name contains unsupported characters.');
    }

    DB::connection('qams_warehouse_admin')->statement(
        "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    $this->info("Warehouse database `$database` is ready.");
})->purpose('Create the QAMS warehouse database if it does not exist');

Artisan::command('ces:sync-warehouse', function (CesWarehouseSynchronizer $synchronizer) {
    $results = $synchronizer->sync();
    $this->info('CES student-level warehouse data refreshed for '.count($results).' campuses.');
})->purpose('ETL student-level CES source data into the QAMS warehouse');

Artisan::command('hrmis:sync-warehouse', function (HrmisWarehouseSynchronizer $synchronizer) {
    $result = $synchronizer->sync();
    $this->info(
        'HRMIS warehouse refreshed: '.$result['employees_loaded'].' employees across '
        .$result['source_campuses'].' source campus IDs.'
    );
})->purpose('Stage HRMIS records and ETL employee snapshots into the QAMS warehouse');
