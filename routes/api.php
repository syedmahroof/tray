<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuilderController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\VisitReportController;
use App\Http\Controllers\Api\MetadataController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::get('/metadata', [MetadataController::class, 'index']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('contacts', ContactController::class);
    Route::apiResource('enquiries', EnquiryController::class);
    Route::apiResource('quotations', QuotationController::class);
    Route::apiResource('visit-reports', VisitReportController::class);
    Route::apiResource('reminders', ReminderController::class);
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('builders', BuilderController::class);
    Route::get('dashboard', [DashboardController::class, '__invoke']);

    // Analytics
    Route::get('analytics', [AnalyticsController::class, 'index']);
    Route::get('analytics/{module}', [AnalyticsController::class, 'module']);
});

