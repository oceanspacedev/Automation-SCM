<?php

namespace App\Http\Controllers;

use App\Models\ProgramSubmission;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ProgramClaimConfirmationController extends Controller
{
    public function __construct(
        protected WhatsAppService $waService
    ) {}

    /**
     * Handle AR and Telemarketing confirmation click via secure signed URL.
     * GET: Render interactive landing page with confirmation buttons (safe from crawler bots).
     * POST: Execute status update and forward workflow based on user button click.
     */
    public function confirm(Request $request, int|string $id): View
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Tautan konfirmasi tidak valid atau sudah kedaluwarsa (berlaku 7 hari). Silakan minta kirim ulang notifikasi ke WhatsApp Anda.');
        }

        $submission = ProgramSubmission::findOrFail($id);

        $role = $request->query('role') ?? $request->input('role');
        if (! $role) {
            $role = ($submission->status_potong_ar === 'DEALER SETUJU (PROSES AR)' || $request->query('action') === 'potong' || $request->input('action') === 'potong')
                ? 'ar'
                : 'telemarketing';
        }

        // Handle POST submission from interactive confirmation page
        if ($request->isMethod('post')) {
            $action = $request->input('action') ?? $request->query('action', 'setuju');
            $arNotification = null;

            if ($action === 'setuju') {
                $submission->update([
                    'status_potong_ar' => 'DEALER SETUJU (PROSES AR)',
                ]);

                // Estafet Otomatis: Kirim notifikasi WA ke Tim AR
                try {
                    $arNotification = $this->waService->sendProgramClaimNotificationToAr($submission);
                    if (! ($arNotification['success'] ?? false)) {
                        Log::warning("Gagal auto-forward WA ke AR untuk submission ID {$submission->id}: ".($arNotification['message'] ?? 'Unknown error'));
                    } else {
                        Log::info("Berhasil auto-forward WA ke AR untuk submission ID {$submission->id}: ".($arNotification['message'] ?? ''));
                    }
                } catch (\Throwable $e) {
                    Log::warning("Gagal auto-forward WA ke AR untuk submission ID {$submission->id}: ".$e->getMessage());
                    $arNotification = [
                        'success' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            } elseif ($action === 'potong') {
                $submission->update([
                    'status_potong_purchase' => 'SUDAH POTONG',
                    'status_potong_ar' => 'DONE',
                    'tgl_potong_tf' => date('d/m/Y'),
                ]);
            } elseif ($action === 'tunda') {
                $submission->update([
                    'status_potong_ar' => 'PENDING DEALER',
                ]);
            }

            return view('program_submissions.confirmed', [
                'submission' => $submission->fresh(),
                'action' => $action,
                'role' => $role,
                'arNotification' => $arNotification,
            ]);
        }

        // GET request: Display landing page with action buttons (safe against web crawlers / WhatsApp preview bots)
        return view('program_submissions.confirm', [
            'submission' => $submission,
            'role' => $role,
        ]);
    }
}
