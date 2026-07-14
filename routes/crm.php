<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VisitReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('contacts/analytics', [ContactController::class, 'analytics'])
        ->middleware('permission:contacts.view')
        ->name('contacts.analytics');

    Route::get('contacts/export', [ContactController::class, 'export'])
        ->middleware('permission:contacts.view')
        ->name('contacts.export');

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

    Route::get('visit-reports/export', [VisitReportController::class, 'export'])
        ->middleware('permission:visit-reports.view')
        ->name('visit-reports.export');

    Route::resource('visit-reports', VisitReportController::class)
        ->middlewareFor(['index', 'show'], 'permission:visit-reports.view')
        ->middlewareFor(['create', 'store'], 'permission:visit-reports.create')
        ->middlewareFor('edit', 'permission:visit-reports.view')
        ->middlewareFor('update', 'permission:visit-reports.update')
        ->middlewareFor('destroy', 'permission:visit-reports.delete');

    Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])
        ->middleware('permission:quotations.view')
        ->name('quotations.pdf');

    Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])
        ->middleware('permission:quotations.view')
        ->name('quotations.print');

    Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])
        ->middleware('permission:quotations.update')
        ->name('quotations.status');

    Route::post('quotations/{quotation}/email', [QuotationController::class, 'email'])
        ->middleware('permission:quotations.send')
        ->name('quotations.email');

    Route::post('quotations/{quotation}/revise', [QuotationController::class, 'revise'])
        ->middleware('permission:quotations.create')
        ->name('quotations.revise');

    Route::get('quotations/analytics', [QuotationController::class, 'analytics'])
        ->middleware('permission:quotations.view')
        ->name('quotations.analytics');

    Route::get('quotations/export', [QuotationController::class, 'export'])
        ->middleware('permission:quotations.view')
        ->name('quotations.export');

    Route::resource('quotations', QuotationController::class)
        ->middlewareFor(['index', 'show'], 'permission:quotations.view')
        ->middlewareFor(['create', 'store'], 'permission:quotations.create')
        ->middlewareFor('edit', 'permission:quotations.view')
        ->middlewareFor('update', 'permission:quotations.update')
        ->middlewareFor('destroy', 'permission:quotations.delete');

    Route::get('reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('reports.index');
});

// Public, signed share link for a quotation PDF (no authentication required).
Route::get('quotations/{quotation}/shared', [QuotationController::class, 'shared'])
    ->middleware('signed')
    ->name('quotations.shared');
