<?php

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\ContactTypeController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\DistrictController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProjectCategoryController;
use App\Http\Controllers\Admin\StateController;
use App\Http\Controllers\LocationLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('location/states', [LocationLookupController::class, 'states'])->name('location.states');
    Route::get('location/districts', [LocationLookupController::class, 'districts'])->name('location.districts');

    Route::resource('countries', CountryController::class)
        ->except('show')
        ->middlewareFor('index', 'permission:countries.view')
        ->middlewareFor(['create', 'store'], 'permission:countries.create')
        ->middlewareFor('edit', 'permission:countries.view')
        ->middlewareFor('update', 'permission:countries.update')
        ->middlewareFor('destroy', 'permission:countries.delete');

    Route::resource('countries.states', StateController::class)
        ->except('show')
        ->scoped()
        ->middlewareFor('index', 'permission:states.view')
        ->middlewareFor(['create', 'store'], 'permission:states.create')
        ->middlewareFor('edit', 'permission:states.view')
        ->middlewareFor('update', 'permission:states.update')
        ->middlewareFor('destroy', 'permission:states.delete');

    Route::resource('states.districts', DistrictController::class)
        ->except('show')
        ->scoped()
        ->middlewareFor('index', 'permission:districts.view')
        ->middlewareFor(['create', 'store'], 'permission:districts.create')
        ->middlewareFor('edit', 'permission:districts.view')
        ->middlewareFor('update', 'permission:districts.update')
        ->middlewareFor('destroy', 'permission:districts.delete');

    Route::resource('project-categories', ProjectCategoryController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:project-categories.view')
        ->middlewareFor('store', 'permission:project-categories.create')
        ->middlewareFor('update', 'permission:project-categories.update')
        ->middlewareFor('destroy', 'permission:project-categories.delete');

    Route::resource('product-categories', ProductCategoryController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:product-categories.view')
        ->middlewareFor('store', 'permission:product-categories.create')
        ->middlewareFor('update', 'permission:product-categories.update')
        ->middlewareFor('destroy', 'permission:product-categories.delete');

    Route::resource('contact-types', ContactTypeController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:contact-types.view')
        ->middlewareFor('store', 'permission:contact-types.create')
        ->middlewareFor('update', 'permission:contact-types.update')
        ->middlewareFor('destroy', 'permission:contact-types.delete');

    Route::resource('brands', BrandController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:brands.view')
        ->middlewareFor('store', 'permission:brands.create')
        ->middlewareFor('update', 'permission:brands.update')
        ->middlewareFor('destroy', 'permission:brands.delete');
});
