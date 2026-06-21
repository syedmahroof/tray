<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('global-search', GlobalSearchController::class)->name('global-search');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/masters.php';
require __DIR__.'/catalog.php';
require __DIR__.'/crm.php';
