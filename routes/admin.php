<?php

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('branches', BranchController::class)
        ->except('show')
        ->middlewareFor('index', 'permission:branches.view')
        ->middlewareFor(['create', 'store'], 'permission:branches.create')
        ->middlewareFor('edit', 'permission:branches.view')
        ->middlewareFor('update', 'permission:branches.update')
        ->middlewareFor('destroy', 'permission:branches.delete');

    Route::resource('roles', RoleController::class)
        ->except('show')
        ->middlewareFor('index', 'permission:roles.view')
        ->middlewareFor(['create', 'store'], 'permission:roles.create')
        ->middlewareFor('edit', 'permission:roles.view')
        ->middlewareFor('update', 'permission:roles.update')
        ->middlewareFor('destroy', 'permission:roles.delete');

    Route::resource('users', UserController::class)
        ->except('show')
        ->middlewareFor('index', 'permission:users.view')
        ->middlewareFor(['create', 'store'], 'permission:users.create')
        ->middlewareFor('edit', 'permission:users.view')
        ->middlewareFor('update', 'permission:users.update')
        ->middlewareFor('destroy', 'permission:users.delete');
});
