<?php

use App\Http\Controllers\Admin\BuilderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('builders/export', [BuilderController::class, 'export'])
        ->middleware('permission:builders.view')
        ->name('builders.export');

    Route::resource('builders', BuilderController::class)
        ->middlewareFor(['index', 'show'], 'permission:builders.view')
        ->middlewareFor(['create', 'store'], 'permission:builders.create')
        ->middlewareFor('edit', 'permission:builders.view')
        ->middlewareFor('update', 'permission:builders.update')
        ->middlewareFor('destroy', 'permission:builders.delete');

    Route::get('projects/analytics', [ProjectController::class, 'analytics'])
        ->middleware('permission:projects.view')
        ->name('projects.analytics');

    Route::get('projects/export', [ProjectController::class, 'export'])
        ->middleware('permission:projects.view')
        ->name('projects.export');

    Route::resource('projects', ProjectController::class)
        ->middlewareFor(['index', 'show'], 'permission:projects.view')
        ->middlewareFor(['create', 'store'], 'permission:projects.create')
        ->middlewareFor('edit', 'permission:projects.view')
        ->middlewareFor('update', 'permission:projects.update')
        ->middlewareFor('destroy', 'permission:projects.delete');

    Route::get('products/export', [ProductController::class, 'export'])
        ->middleware('permission:products.view')
        ->name('products.export');

    Route::resource('products', ProductController::class)
        ->middlewareFor(['index', 'show'], 'permission:products.view')
        ->middlewareFor(['create', 'store'], 'permission:products.create')
        ->middlewareFor('edit', 'permission:products.view')
        ->middlewareFor('update', 'permission:products.update')
        ->middlewareFor('destroy', 'permission:products.delete');
});
