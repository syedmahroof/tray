<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\VisitReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('contacts', ContactController::class)
        ->middlewareFor(['index', 'show'], 'permission:contacts.view')
        ->middlewareFor(['create', 'store'], 'permission:contacts.create')
        ->middlewareFor('edit', 'permission:contacts.view')
        ->middlewareFor('update', 'permission:contacts.update')
        ->middlewareFor('destroy', 'permission:contacts.delete');

    Route::resource('customers', CustomerController::class)
        ->middlewareFor(['index', 'show'], 'permission:customers.view')
        ->middlewareFor(['create', 'store'], 'permission:customers.create')
        ->middlewareFor('edit', 'permission:customers.view')
        ->middlewareFor('update', 'permission:customers.update')
        ->middlewareFor('destroy', 'permission:customers.delete');

    Route::get('enquiries/kanban', [EnquiryController::class, 'kanban'])
        ->middleware('permission:enquiries.view')
        ->name('enquiries.kanban');
    Route::patch('enquiries/{enquiry}/status', [EnquiryController::class, 'updateStatus'])
        ->middleware('permission:enquiries.update')
        ->name('enquiries.status');

    Route::resource('enquiries', EnquiryController::class)
        ->middlewareFor(['index', 'show'], 'permission:enquiries.view')
        ->middlewareFor(['create', 'store'], 'permission:enquiries.create')
        ->middlewareFor('edit', 'permission:enquiries.view')
        ->middlewareFor('update', 'permission:enquiries.update')
        ->middlewareFor('destroy', 'permission:enquiries.delete');

    Route::post('contacts/{contact}/notes', [NoteController::class, 'storeForContact'])
        ->middleware('permission:notes.create')
        ->name('contacts.notes.store');
    Route::post('enquiries/{enquiry}/notes', [NoteController::class, 'storeForEnquiry'])
        ->middleware('permission:notes.create')
        ->name('enquiries.notes.store');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])
        ->middleware('permission:notes.delete')
        ->name('notes.destroy');

    Route::get('reminders', [ReminderController::class, 'index'])
        ->middleware('permission:reminders.view')
        ->name('reminders.index');

    Route::post('contacts/{contact}/reminders', [ReminderController::class, 'storeForContact'])
        ->middleware('permission:reminders.create')
        ->name('contacts.reminders.store');
    Route::post('enquiries/{enquiry}/reminders', [ReminderController::class, 'storeForEnquiry'])
        ->middleware('permission:reminders.create')
        ->name('enquiries.reminders.store');
    Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy'])
        ->middleware('permission:reminders.delete')
        ->name('reminders.destroy');

    Route::get('visit-reports/analytics', [VisitReportController::class, 'analytics'])
        ->middleware('permission:visit-reports.view')
        ->name('visit-reports.analytics');

    Route::resource('visit-reports', VisitReportController::class)
        ->middlewareFor(['index', 'show'], 'permission:visit-reports.view')
        ->middlewareFor(['create', 'store'], 'permission:visit-reports.create')
        ->middlewareFor('edit', 'permission:visit-reports.view')
        ->middlewareFor('update', 'permission:visit-reports.update')
        ->middlewareFor('destroy', 'permission:visit-reports.delete');
});
