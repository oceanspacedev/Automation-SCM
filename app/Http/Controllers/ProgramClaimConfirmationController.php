<?php

namespace App\Http\Controllers;

use App\Models\ProgramSubmission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgramClaimConfirmationController extends Controller
{
    /**
     * Handle AR confirmation click via secure signed URL.
     */
    public function confirm(Request $request, int|string $id): View
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Tautan konfirmasi tidak valid atau sudah kedaluwarsa. Silakan minta kirim ulang notifikasi ke WhatsApp Anda.');
        }

        $submission = ProgramSubmission::findOrFail($id);
        $action = $request->query('action', 'potong');

        if ($action === 'potong') {
            $submission->update([
                'status_potong_purchase' => 'SUDAH POTONG',
                'status_potong_ar' => 'DONE',
                'tgl_potong_tf' => date('d/m/Y'),
            ]);
        } elseif ($action === 'tunda') {
            $submission->update([
                'status_potong_ar' => 'PENDING',
            ]);
        }

        return view('program_submissions.confirmed', [
            'submission' => $submission->fresh(),
            'action' => $action,
        ]);
    }
}
