<?php

use App\Http\Controllers\ArContactController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DataProgramController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\DraftImportController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MasterProgramController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramClaimConfirmationController;
use App\Http\Controllers\ProgramSubmissionController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\SetAreaContext;
use App\Http\Middleware\SetBrandContext;
use Illuminate\Support\Facades\Route;

// Google OAuth Authentication
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

// Invoice preview & PDF download
Route::get('/invoices/{id}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');

// Program Claim Confirmation Action Link (Landing Page on GET, Form Submit on POST)
Route::match(['get', 'post'], '/p/confirm/{id}', [ProgramClaimConfirmationController::class, 'confirm'])->name('program-submissions.confirm');
Route::match(['get', 'post'], '/dp/confirm/{id}', [ProgramClaimConfirmationController::class, 'confirmDataProgram'])->name('data-programs.confirm');

// API Endpoints
// Area (Jabar/Jateng) dipilih lewat header X-Area atau parameter ?area=, default Jabar;
// brand (Realme, Oppo, ...) lewat header X-Brand atau parameter ?brand=, default Realme
Route::prefix('api')->middleware([SetAreaContext::class, SetBrandContext::class])->group(function () {
    // Master Brand (Realme, Oppo, ...)
    Route::get('/brands', [BrandController::class, 'index']);
    Route::post('/brands', [BrandController::class, 'store']);
    Route::put('/brands/{id}', [BrandController::class, 'update']);
    Route::delete('/brands/{id}', [BrandController::class, 'destroy']);
    Route::patch('/brands/{id}/toggle-status', [BrandController::class, 'toggleStatus']);

    // Master Programs
    Route::get('/programs', [MasterProgramController::class, 'index']);
    Route::get('/programs/export', [MasterProgramController::class, 'export']);
    Route::get('/programs/options', [MasterProgramController::class, 'options']);
    Route::post('/programs', [MasterProgramController::class, 'store']);
    Route::post('/programs/import', [MasterProgramController::class, 'import']);

    // Master Regions (per area)
    Route::get('/regions', [RegionController::class, 'index']);
    Route::get('/regions/export', [RegionController::class, 'export']);
    Route::get('/regions/options', [RegionController::class, 'options']);
    Route::post('/regions', [RegionController::class, 'store']);
    Route::put('/regions/{id}', [RegionController::class, 'update']);
    Route::delete('/regions/{id}', [RegionController::class, 'destroy']);
    Route::patch('/regions/{id}/toggle-status', [RegionController::class, 'toggleStatus']);

    // Master AR (per region, per area)
    Route::get('/ar-contacts', [ArContactController::class, 'index']);
    Route::get('/ar-contacts/export', [ArContactController::class, 'export']);
    Route::post('/ar-contacts', [ArContactController::class, 'store']);
    Route::put('/ar-contacts/{id}', [ArContactController::class, 'update']);
    Route::delete('/ar-contacts/{id}', [ArContactController::class, 'destroy']);
    Route::patch('/ar-contacts/{id}/toggle-status', [ArContactController::class, 'toggleStatus']);
    Route::put('/programs/{id}', [MasterProgramController::class, 'update']);
    Route::delete('/programs/{id}', [MasterProgramController::class, 'destroy']);
    Route::patch('/programs/{id}/toggle-status', [MasterProgramController::class, 'toggleStatus']);

    // Master Users
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::patch('/users/{id}/approve', [UserController::class, 'approve']);
    Route::get('/telemarketing-contacts', [UserController::class, 'telemarketingContacts']);

    // Master Customers
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/export', [CustomerController::class, 'export']);
    Route::get('/customers/options', [CustomerController::class, 'options']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);
    Route::patch('/customers/{id}/toggle-status', [CustomerController::class, 'toggleStatus']);

    // Master Dealers
    Route::get('/dealers', [DealerController::class, 'index']);
    Route::get('/dealers/export', [DealerController::class, 'export']);
    Route::get('/dealers/lookup', [DealerController::class, 'lookup']);
    Route::get('/dealers/suggestions', [DealerController::class, 'searchSuggestions']);
    Route::post('/dealers/import', [DealerController::class, 'import']);
    Route::get('/dealers/import/skipped', [DealerController::class, 'downloadSkippedImportRows']);
    Route::post('/dealers', [DealerController::class, 'store']);
    Route::put('/dealers/{id}', [DealerController::class, 'update']);
    Route::delete('/dealers/{id}', [DealerController::class, 'destroy']);
    Route::patch('/dealers/{id}/toggle-status', [DealerController::class, 'toggleStatus']);

    // Auth (Email + Password)
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Profil akun yang sedang login
    Route::middleware('auth')->group(function () {
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);
        Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    });

    // Auth (WhatsApp OTP)
    Route::post('/auth/wa-otp/send', [AuthController::class, 'sendWaOtp']);
    Route::post('/auth/wa-otp/verify', [AuthController::class, 'verifyWaOtp']);

    // Google OAuth Status & Disconnect
    Route::get('/google/status', [GoogleAuthController::class, 'status']);
    Route::post('/google/disconnect', [GoogleAuthController::class, 'disconnect']);

    // Dashboard
    Route::get('/dashboard', [InvoiceController::class, 'dashboard']);

    // Drafts
    Route::post('/drafts/import', [DraftImportController::class, 'import']);
    Route::get('/drafts', [DraftController::class, 'index']);
    Route::post('/drafts', [DraftController::class, 'store']);
    Route::post('/drafts/push-batch-to-data-program', [DraftController::class, 'pushBatchToDataProgram']);
    Route::get('/drafts/{id}', [DraftController::class, 'show']);
    Route::delete('/drafts/{id}', [DraftController::class, 'destroy']);
    Route::post('/drafts/{id}/validate', [DraftController::class, 'validateDraft']);
    Route::post('/drafts/{id}/push-to-data-program', [DraftController::class, 'pushToDataProgram']);

    // Invoices & Email Accounts
    Route::get('/email-accounts', [InvoiceController::class, 'emailAccounts']);
    Route::get('/bill-to-options', [InvoiceController::class, 'billToOptions']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::post('/invoices/generate-all', [InvoiceController::class, 'generateAll']);
    Route::post('/invoices/generate-batch', [InvoiceController::class, 'generateAll']);
    Route::post('/invoices/generate/{draftId}', [InvoiceController::class, 'generate']);
    Route::post('/invoices/quick-send-all', [InvoiceController::class, 'quickSendAll']);
    Route::post('/invoices/send-batch', [InvoiceController::class, 'sendBatch']);
    Route::post('/invoices/{id}/send', [InvoiceController::class, 'sendEmail']);
    Route::post('/invoices/{id}/send-email', [InvoiceController::class, 'sendEmail']);
    Route::post('/invoices/{id}/quick-send-email', [InvoiceController::class, 'quickSendEmail']);
    Route::get('/email-logs', [InvoiceController::class, 'emailLogs']);
    Route::get('/whatsapp-logs', [InvoiceController::class, 'whatsAppLogs']);

    // Program Submissions (Google Sheets / Form REALME)
    Route::get('/program-submissions', [ProgramSubmissionController::class, 'index']);
    Route::get('/program-submissions/export', [ProgramSubmissionController::class, 'export']);
    Route::patch('/program-submissions/{id}', [ProgramSubmissionController::class, 'update']);
    Route::delete('/program-submissions/{id}', [ProgramSubmissionController::class, 'destroy']);
    Route::post('/program-submissions/{id}/swap-docs', [ProgramSubmissionController::class, 'swapDocs']);
    Route::post('/program-submissions/{id}/push-spreadsheet', [ProgramSubmissionController::class, 'pushToSpreadsheet']);
    Route::post('/program-submissions/sync', [ProgramSubmissionController::class, 'sync']);
    Route::get('/program-submissions/config', [ProgramSubmissionController::class, 'getConfig']);
    Route::post('/program-submissions/config', [ProgramSubmissionController::class, 'saveConfig']);
    Route::post('/program-submissions/{id}/analyze-ai', [ProgramSubmissionController::class, 'analyzeAi']);
    Route::post('/program-submissions/analyze-ai-batch', [ProgramSubmissionController::class, 'analyzeAiBatch']);
    Route::get('/program-submissions/ai-config', [ProgramSubmissionController::class, 'getAiConfig']);
    Route::post('/program-submissions/ai-config', [ProgramSubmissionController::class, 'saveAiConfig']);
    Route::get('/program-submissions/ai-status', [ProgramSubmissionController::class, 'getAiStatus']);
    Route::post('/program-submissions/ai-run-background', [ProgramSubmissionController::class, 'runAiInBackground']);
    Route::post('/program-submissions/ai-test', [ProgramSubmissionController::class, 'testAiConnection']);
    Route::post('/program-submissions/{id}/send-wa-ar', [ProgramSubmissionController::class, 'sendWaToAr']);
    Route::post('/program-submissions/{id}/send-wa-telemarketing', [ProgramSubmissionController::class, 'sendWaToTelemarketing']);
    Route::post('/program-submissions/{id}/send-wa-doc-error', [ProgramSubmissionController::class, 'sendWaDocError']);
    Route::post('/webhooks/form-program', [ProgramSubmissionController::class, 'webhook']);
    Route::get('/program-submissions/form-options', [ProgramSubmissionController::class, 'formOptions']);
    Route::post('/program-submissions/pre-validate', [ProgramSubmissionController::class, 'preValidateForm']);
    Route::post('/program-submissions/submit-form', [ProgramSubmissionController::class, 'submitForm']);

    // Data Program (56 Kolom Google Spreadsheet)
    Route::get('/data-program', [DataProgramController::class, 'index']);
    Route::post('/data-program/sync', [DataProgramController::class, 'sync']);
    Route::get('/data-program/url', [DataProgramController::class, 'getUrl']);
    Route::post('/data-program/url', [DataProgramController::class, 'saveUrl']);
    Route::get('/data-program/export', [DataProgramController::class, 'export']);
    Route::post('/data-program/reconcile', [DataProgramController::class, 'reconcile']);
    Route::post('/data-program/{id}/reconcile', [DataProgramController::class, 'reconcileRow']);
    Route::post('/data-program/{id}/send-wa-telemarketing', [DataProgramController::class, 'sendWaToTelemarketing']);
    Route::post('/data-program/{id}/send-wa-ar', [DataProgramController::class, 'sendWaToAr']);
    Route::get('/data-program/reconcile-stats', [DataProgramController::class, 'reconcileStats']);
    Route::get('/data-program/reconciliation-logs', [DataProgramController::class, 'reconciliationLogs']);
    Route::put('/data-program/bulk', [DataProgramController::class, 'bulkUpdate']);
    Route::put('/data-program/{id}', [DataProgramController::class, 'update']);
    Route::post('/webhooks/data-program', [DataProgramController::class, 'webhook']);
});

// SPA catch-all
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|auth|p\/confirm|dp\/confirm|invoices\/[0-9]+\/(preview|pdf)|storage).*$');
