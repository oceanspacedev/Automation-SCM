<?php

use App\Http\Controllers\DraftController;
use App\Http\Controllers\DraftImportController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

// Invoice preview & PDF download
Route::get('/invoices/{id}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');

// API Endpoints
Route::prefix('api')->group(function () {
    // Dashboard
    Route::get('/dashboard', [InvoiceController::class, 'dashboard']);

    // Drafts
    Route::post('/drafts/import', [DraftImportController::class, 'import']);
    Route::get('/drafts', [DraftController::class, 'index']);
    Route::get('/drafts/{id}', [DraftController::class, 'show']);
    Route::post('/drafts/{id}/validate', [DraftController::class, 'validateDraft']);

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::post('/invoices/generate-all', [InvoiceController::class, 'generateAll']);
    Route::post('/invoices/generate/{draftId}', [InvoiceController::class, 'generate']);
    Route::post('/invoices/{id}/send-email', [InvoiceController::class, 'sendEmail']);
    Route::get('/email-logs', [InvoiceController::class, 'emailLogs']);
});

// SPA catch-all
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|invoices\/[0-9]+\/(preview|pdf)|storage).*$');
