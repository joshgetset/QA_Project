<?php

use App\Http\Controllers\Api\CesEnrollmentSummaryController;
use App\Http\Controllers\Api\CesSourceExportController;
use App\Http\Controllers\Api\HrmisSourceExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1', 'ces.api-key'])
    ->get('/ces/enrollment-summary', CesEnrollmentSummaryController::class)
    ->name('ces.api.enrollment-summary');

Route::middleware(['throttle:1200,1', 'source.api-key:hrmis'])
    ->get('/source/hrmis/{dataset}', HrmisSourceExportController::class)
    ->name('source.api.hrmis.export');

Route::middleware(['throttle:1200,1', 'source.api-key:ces'])
    ->prefix('/source/ces/{campus}')
    ->name('source.api.ces.')
    ->group(function (): void {
        Route::get('/manifest', [CesSourceExportController::class, 'manifest'])->name('manifest');
        Route::get('/scholarships', [CesSourceExportController::class, 'scholarships'])->name('scholarships');
        Route::post('/programs', [CesSourceExportController::class, 'programs'])->name('programs');
        Route::post('/students', [CesSourceExportController::class, 'students'])->name('students');
        Route::get('/student-numbers/{dataset}', [CesSourceExportController::class, 'studentNumbers'])->name('student-numbers');
        Route::get('/registrations', [CesSourceExportController::class, 'registrations'])->name('registrations');
        Route::get('/graduation-list', [CesSourceExportController::class, 'graduationList'])->name('graduation-list');
    });
