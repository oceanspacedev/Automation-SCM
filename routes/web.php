<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\DraftImportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProgramClaimConfirmationController;
use App\Http\Controllers\ProgramSubmissionController;
use Illuminate\Support\Facades\Route;

// Invoice preview & PDF download
Route::get('/invoices/{id}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');

// Program Claim Confirmation 1-Click Action Link
Route::get('/p/confirm/{id}', [ProgramClaimConfirmationController::class, 'confirm'])->name('program-submissions.confirm');

// API Endpoints
Route::prefix('api')->group(function () {
    // Auth
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard', [InvoiceController::class, 'dashboard']);

    // Drafts
    Route::post('/drafts/import', [DraftImportController::class, 'import']);
    Route::get('/drafts', [DraftController::class, 'index']);
    Route::get('/drafts/{id}', [DraftController::class, 'show']);
    Route::post('/drafts/{id}/validate', [DraftController::class, 'validateDraft']);

    // Invoices
    Route::get('/bill-to-options', [InvoiceController::class, 'billToOptions']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::post('/invoices/generate-all', [InvoiceController::class, 'generateAll']);
    Route::post('/invoices/generate-batch', [InvoiceController::class, 'generateAll']);
    Route::post('/invoices/generate/{draftId}', [InvoiceController::class, 'generate']);
    Route::post('/invoices/quick-send-all', [InvoiceController::class, 'quickSendAll']);
    Route::post('/invoices/send-batch', [InvoiceController::class, 'sendBatch']);
    Route::post('/invoices/{id}/send-email', [InvoiceController::class, 'sendEmail']);
    Route::post('/invoices/{id}/quick-send-email', [InvoiceController::class, 'quickSendEmail']);
    Route::get('/email-logs', [InvoiceController::class, 'emailLogs']);
    Route::get('/whatsapp-logs', [InvoiceController::class, 'whatsAppLogs']);

    // Program Submissions (Google Sheets / Form REALME)
    Route::get('/program-submissions', [ProgramSubmissionController::class, 'index']);
    Route::get('/program-submissions/export', [ProgramSubmissionController::class, 'export']);
    Route::patch('/program-submissions/{id}', [ProgramSubmissionController::class, 'update']);
    Route::post('/program-submissions/sync', [ProgramSubmissionController::class, 'sync']);
    Route::post('/program-submissions/config', [ProgramSubmissionController::class, 'saveConfig']);
    Route::post('/program-submissions/{id}/analyze-ai', [ProgramSubmissionController::class, 'analyzeAi']);
    Route::post('/program-submissions/analyze-ai-batch', [ProgramSubmissionController::class, 'analyzeAiBatch']);
    Route::get('/program-submissions/ai-config', [ProgramSubmissionController::class, 'getAiConfig']);
    Route::post('/program-submissions/ai-config', [ProgramSubmissionController::class, 'saveAiConfig']);
    Route::get('/program-submissions/ai-status', [ProgramSubmissionController::class, 'getAiStatus']);
    Route::post('/program-submissions/ai-run-background', [ProgramSubmissionController::class, 'runAiInBackground']);
    Route::post('/program-submissions/ai-test', [ProgramSubmissionController::class, 'testAiConnection']);
    Route::post('/program-submissions/{id}/send-wa-ar', [ProgramSubmissionController::class, 'sendWaToAr']);
    Route::post('/webhooks/form-program', [ProgramSubmissionController::class, 'webhook']);
});

// SPA catch-all
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|p\/confirm|invoices\/[0-9]+\/(preview|pdf)|storage).*$');
