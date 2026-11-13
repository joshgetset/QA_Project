<?php

use App\Services\CesAnalyticsSnapshotSynchronizer;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ces:sync-analytics-snapshot', function (CesAnalyticsSnapshotSynchronizer $synchronizer) {
    $synchronizer->sync();
    $this->info('CES analytics snapshot refreshed.');
})->purpose('Refresh the aggregate CES analytics snapshot from the live campus databases');
