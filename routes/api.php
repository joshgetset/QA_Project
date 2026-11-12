<?php

use App\Http\Controllers\Api\CesEnrollmentSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1', 'ces.api-key'])
    ->get('/ces/enrollment-summary', CesEnrollmentSummaryController::class)
    ->name('ces.api.enrollment-summary');
