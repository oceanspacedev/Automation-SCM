<?php

namespace App\Http\Controllers;

use App\Models\DataProgram;
use App\Models\ProgramSubmission;
use App\Services\DataProgramSyncService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ProgramClaimConfirmationController extends Controller
{
    public function __construct(
        protected WhatsAppService $waService,
        protected DataProgramSyncService $dpSyncService
    ) {}

    /**
     * Handle AR and Telemarketing confirmation click via secure signed URL for ProgramSubmission (Form Program).
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

                // Cari dan sinkronkan DataProgram yang cocok jika ada
                $matchingDp = DataProgram::where('kode_bt', $submission->id_real)
                    ->where('status_potong_purchase', 'BISA DI POTONG')
                    ->first();
                if ($matchingDp) {
                    $matchingDp->update(['status_potong_ar' => 'DEALER SETUJU (PROSES AR)']);
                    $this->pushDpToSpreadsheet($matchingDp);
                }
            } elseif ($action === 'potong') {
                $todayDate = date('n/j/Y');
                $submission->update([
                    'status_potong_purchase' => 'SUDAH POTONG',
                    'status_potong_ar' => 'DONE',
                    'tgl_potong_tf' => $todayDate,
                ]);

                $matchingDp = DataProgram::where('kode_bt', $submission->id_real)
                    ->whereIn('status_potong_purchase', ['BISA DI POTONG', 'SUDAH POTONG'])
                    ->first();
                if ($matchingDp) {
                    $matchingDp->update([
                        'status_potong_purchase' => 'SUDAH POTONG',
                        'status_potong_ar' => 'DONE',
                        'tgl_potong_tf' => $todayDate,
                    ]);
                    $this->pushDpToSpreadsheet($matchingDp);
                }
            } elseif ($action === 'tunda') {
                $submission->update([
                    'status_potong_ar' => 'PENDING DEALER',
                ]);

                $matchingDp = DataProgram::where('kode_bt', $submission->id_real)
                    ->where('status_potong_purchase', 'BISA DI POTONG')
                    ->first();
                if ($matchingDp) {
                    $matchingDp->update(['status_potong_ar' => 'PENDING DEALER']);
                    $this->pushDpToSpreadsheet($matchingDp);
                }
            }

            return view('program_submissions.confirmed', [
                'submission' => $submission->fresh(),
                'action' => $action,
                'role' => $role,
                'arNotification' => $arNotification,
            ]);
        }

        // GET request: Display landing page with action buttons
        return view('program_submissions.confirm', [
            'submission' => $submission,
            'role' => $role,
        ]);
    }

    /**
     * Handle AR and Telemarketing confirmation click via secure signed URL for DataProgram.
     */
    public function confirmDataProgram(Request $request, int|string $id): View
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Tautan konfirmasi tidak valid atau sudah kedaluwarsa (berlaku 7 hari). Silakan minta kirim ulang notifikasi ke WhatsApp Anda.');
        }

        $dp = DataProgram::findOrFail($id);

        $role = $request->query('role') ?? $request->input('role');
        if (! $role) {
            $role = ($dp->status_potong_ar === 'DEALER SETUJU (PROSES AR)' || $request->query('action') === 'potong' || $request->input('action') === 'potong')
                ? 'ar'
                : 'telemarketing';
        }

        // Handle POST submission from interactive confirmation page
        if ($request->isMethod('post')) {
            $action = $request->input('action') ?? $request->query('action', 'setuju');
            $arNotification = null;

            if ($action === 'setuju') {
                $dp->update([
                    'status_potong_ar' => 'DEALER SETUJU (PROSES AR)',
                ]);

                // Estafet Otomatis: Kirim notifikasi WA ke Tim AR
                try {
                    $arNotification = $this->waService->sendDataProgramClaimNotificationToAr($dp);
                    if (! ($arNotification['success'] ?? false)) {
                        Log::warning("Gagal auto-forward WA ke AR untuk DataProgram ID {$dp->id}: ".($arNotification['message'] ?? 'Unknown error'));
                    } else {
                        Log::info("Berhasil auto-forward WA ke AR untuk DataProgram ID {$dp->id}: ".($arNotification['message'] ?? ''));
                    }
                } catch (\Throwable $e) {
                    Log::warning("Gagal auto-forward WA ke AR untuk DataProgram ID {$dp->id}: ".$e->getMessage());
                    $arNotification = [
                        'success' => false,
                        'message' => $e->getMessage(),
                    ];
                }

                // Kirim perubahan status_potong_ar ke Google Spreadsheet
                $this->pushDpToSpreadsheet($dp);
            } elseif ($action === 'potong') {
                $todayDate = date('n/j/Y');
                $dp->update([
                    'status_potong_purchase' => 'SUDAH POTONG',
                    'status_potong_ar' => 'DONE',
                    'tgl_potong_tf' => $todayDate,
                ]);

                // Kirim status SUDAH POTONG ke Google Spreadsheet
                $this->pushDpToSpreadsheet($dp);
            } elseif ($action === 'tunda') {
                $dp->update([
                    'status_potong_ar' => 'PENDING DEALER',
                ]);

                // Kirim status PENDING DEALER ke Google Spreadsheet
                $this->pushDpToSpreadsheet($dp);
            }

            return view('data_programs.confirmed', [
                'dp' => $dp->fresh(),
                'action' => $action,
                'role' => $role,
                'arNotification' => $arNotification,
            ]);
        }

        // GET request: Display landing page with action buttons
        return view('data_programs.confirm', [
            'dp' => $dp,
            'role' => $role,
        ]);
    }

    /**
     * Push status update to Google Spreadsheet master.
     */
    protected function pushDpToSpreadsheet(DataProgram $dp): void
    {
        try {
            $this->dpSyncService->pushUpdatesToSpreadsheet([[
                'kode_bt' => $dp->kode_bt,
                'dealer_name' => $dp->dealer_name,
                'program' => $dp->program,
                'program_name' => $dp->program_name,
                'periode' => $dp->periode,
                'status_potong_purchase' => $dp->status_potong_purchase,
                'status_potong_ar' => $dp->status_potong_ar,
                'tgl_potong_tf' => $dp->tgl_potong_tf,
            ]]);
        } catch (\Throwable $e) {
            Log::warning("Gagal push status potong ke spreadsheet untuk DP ID {$dp->id}: {$e->getMessage()}");
        }
    }
}
