<?php

namespace App\Http\Controllers;

use App\Models\DataProgram;
use App\Models\ProgramApprovalLog;
use App\Models\ProgramSubmission;
use App\Models\TeamContact;
use App\Services\DataProgramSyncService;
use App\Services\ProgramApprovalFlow;
use App\Services\ProgramDocumentStorage;
use App\Services\ProgramReconciliationService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgramClaimConfirmationController extends Controller
{
    /**
     * What each Data Program link may do: telemarketing records the dealer's choice (cut, transfer or postpone), AR
     * cuts (or postpones the cut), the SCM team confirms a transfer, the tax team saves the tax check.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_ACTIONS = [
        'telemarketing' => ['setuju', 'setuju_transfer', 'tunda'],
        'ar' => ['potong', 'tunda'],
        'scm' => ['transfer'],
        'pajak' => ['cek_pajak'],
    ];

    /**
     * The approval stages (ProgramApprovalFlow) in which each action may run: the dealer's choice only before the claim
     * is settled, the cut only while AR is waiting, the transfer only while SCM is waiting, the tax check once settled.
     *
     * @var array<string, list<string>>
     */
    private const ACTION_STAGES = [
        'setuju' => ['waiting_telemarketing', 'waiting_approval', 'postponed', 'approved', 'awaiting_transfer'],
        'setuju_transfer' => ['waiting_telemarketing', 'waiting_approval', 'postponed', 'approved', 'awaiting_transfer'],
        'tunda' => ['waiting_telemarketing', 'waiting_approval', 'postponed', 'approved', 'awaiting_transfer'],
        'potong' => ['approved'],
        'transfer' => ['awaiting_transfer'],
        'cek_pajak' => ['cut', 'tax_checked'],
    ];

    public function __construct(
        protected WhatsAppService $waService,
        protected DataProgramSyncService $dpSyncService,
        protected ProgramReconciliationService $reconciliationService
    ) {}

    /**
     * Handle AR and Telemarketing confirmation click via secure signed URL for ProgramSubmission (Form Program).
     */
    public function confirm(Request $request, int|string $id): View
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Tautan konfirmasi tidak valid atau sudah kedaluwarsa (berlaku '.WhatsAppService::CONFIRM_LINK_DAYS.' hari). Silakan minta kirim ulang notifikasi ke WhatsApp Anda.');
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
            $taxNotification = null;

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
                $matchingDp = $this->reconciliationService->dataProgramForSubmission($submission);
                if ($matchingDp) {
                    $matchingDp->update(['status_potong_ar' => 'DEALER SETUJU (PROSES AR)']);
                    $this->pushDpToSpreadsheet($matchingDp);
                }
                ProgramApprovalLog::record($matchingDp, ProgramApprovalLog::APPROVED, actor: $this->linkActor($role), submission: $submission);
                ProgramApprovalLog::record($matchingDp, ProgramApprovalLog::AR_NOTIFIED, (bool) ($arNotification['success'] ?? false),
                    actor: ProgramApprovalLog::AUTOMATIC, recipient: ProgramApprovalLog::arRecipient($matchingDp), note: $arNotification['message'] ?? null, submission: $submission);
            } elseif ($action === 'potong') {
                $todayDate = date('n/j/Y');
                $submission->update([
                    'status_potong_purchase' => 'SUDAH POTONG',
                    'status_potong_ar' => 'DONE',
                    'tgl_potong_tf' => $todayDate,
                ]);

                $matchingDp = $this->reconciliationService->dataProgramForSubmission($submission);
                if ($matchingDp) {
                    $updateData = [
                        'status_potong_purchase' => 'SUDAH POTONG',
                        'status_potong_ar' => 'DONE',
                        'tgl_potong_tf' => $todayDate,
                    ];
                    if (empty($matchingDp->cn) && ! empty($submission->credit_note_file)) {
                        $updateData['cn'] = $submission->credit_note_file;
                    }
                    if (empty($matchingDp->agrement) && ! empty($submission->agreement_file)) {
                        $updateData['agrement'] = $submission->agreement_file;
                    }
                    if (empty($matchingDp->cek_fp) && ! empty($submission->tax_invoice_file)) {
                        $updateData['cek_fp'] = $submission->tax_invoice_file;
                    }
                    $matchingDp->update($updateData);
                    $this->pushDpToSpreadsheet($matchingDp);
                }
                ProgramApprovalLog::record($matchingDp, ProgramApprovalLog::CUT, actor: $this->linkActor($role), submission: $submission);
                $taxNotification = $matchingDp ? $this->notifyTaxTeam($matchingDp) : null;
            } elseif ($action === 'tunda') {
                $submission->update([
                    'status_potong_ar' => 'PENDING DEALER',
                ]);

                $matchingDp = $this->reconciliationService->dataProgramForSubmission($submission);
                if ($matchingDp) {
                    $matchingDp->update(['status_potong_ar' => 'PENDING DEALER']);
                    $this->pushDpToSpreadsheet($matchingDp);
                }
                ProgramApprovalLog::record($matchingDp, ProgramApprovalLog::POSTPONED, actor: $this->linkActor($role), submission: $submission);
            }

            return view('program_submissions.confirmed', [
                'submission' => $submission->fresh(),
                'action' => $action,
                'role' => $role,
                'arNotification' => $arNotification,
                'taxNotification' => $taxNotification,
            ]);
        }

        // GET request: Display landing page with action buttons
        return view('program_submissions.confirm', [
            'submission' => $submission,
            'role' => $role,
        ]);
    }

    /**
     * Handle AR, Telemarketing and Tim Pajak confirmation click via secure signed URL for DataProgram.
     */
    public function confirmDataProgram(Request $request, int|string $id): View
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Tautan konfirmasi tidak valid atau sudah kedaluwarsa (berlaku '.WhatsAppService::CONFIRM_LINK_DAYS.' hari). Silakan minta kirim ulang notifikasi ke WhatsApp Anda.');
        }

        $dp = DataProgram::findOrFail($id);

        // Peran hanya dari tautan yang ditandatangani; tautan lama tanpa peran ditebak dari status (AR atau telemarketing)
        $role = $request->query('role');
        if (! $role) {
            $role = ($dp->status_potong_ar === 'DEALER SETUJU (PROSES AR)' || $request->query('action') === 'potong')
                ? 'ar'
                : 'telemarketing';
        }
        $stage = app(ProgramApprovalFlow::class)->stage($dp);

        // Handle POST submission from interactive confirmation page
        if ($request->isMethod('post')) {
            $action = $request->input('action') ?? $request->query('action', 'setuju');
            $arNotification = null;
            $taxNotification = null;
            $scmNotification = null;
            $scmTransferNotification = null;

            // Tiap tautan hanya untuk aksi perannya (potong / transfer dipilih telemarketing, transfer dikonfirmasi SCM)
            if (! in_array($action, self::ROLE_ACTIONS[$role] ?? [], true)) {
                abort(403, 'Aksi ini tidak tersedia untuk tautan ini.');
            }

            // Tautan lama tidak boleh memproses klaim yang sudah selesai atau dialihkan (mis. dipotong sekaligus ditransfer)
            if (! in_array($stage, self::ACTION_STAGES[$action], true)) {
                abort(409, 'Klaim ini sudah diproses atau dialihkan, jadi aksi ini tidak bisa dijalankan lagi.');
            }

            if ($action === 'setuju_transfer') {
                // Dealer memilih ditransfer: rekening & KTP/NPWP pemilik rekening diisi telemarketing, lalu diteruskan ke Tim SCM (tanpa AR)
                $validated = $request->validate([
                    'ktp_npwp' => [Rule::requiredIf(! $this->reconciliationService->isValidUrl($dp->cek_evidance)), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
                    'bank' => ['required', 'string', 'max:100', 'regex:/[\pL\pN]/u'],
                    'norek' => ['required', 'string', 'max:100', 'regex:/^[\d\s.\-]+$/', function (string $attribute, mixed $value, \Closure $fail) {
                        if (strlen(preg_replace('/\D/', '', (string) $value)) < 5) {
                            $fail('No. rekening minimal 5 angka.');
                        }
                    }],
                    'namrek' => ['required', 'string', 'max:255', 'regex:/[\pL\pN]/u'],
                ], [
                    'bank.required' => 'Nama bank wajib diisi.',
                    'bank.regex' => 'Nama bank wajib diisi.',
                    'norek.required' => 'No. rekening wajib diisi.',
                    'norek.regex' => 'No. rekening hanya boleh berisi angka.',
                    'namrek.required' => 'Nama pemilik rekening wajib diisi.',
                    'namrek.regex' => 'Nama pemilik rekening wajib diisi.',
                    'ktp_npwp.required' => 'Foto KTP atau NPWP pemilik rekening wajib diunggah.',
                    'ktp_npwp.mimes' => 'KTP / NPWP harus berupa PDF atau gambar (JPG, PNG, WEBP).',
                    'ktp_npwp.max' => 'Ukuran file KTP / NPWP maksimal 15 MB.',
                    'ktp_npwp.uploaded' => 'File KTP / NPWP gagal diunggah. Coba lagi.',
                ]);

                $dp->update([
                    'status_potong_ar' => DataProgram::TRANSFER_APPROVED,
                    'tf_status' => 'TF',
                    'bank' => trim($validated['bank']),
                    'norek' => trim($validated['norek']),
                    'namrek' => trim($validated['namrek']),
                    // KTP / NPWP masuk ke kolom Evid
                    'cek_evidance' => $request->hasFile('ktp_npwp')
                        ? app(ProgramDocumentStorage::class)->store($request->file('ktp_npwp'), 'KTP_NPWP', (string) $dp->dealer_name, (string) ($dp->program_name ?: $dp->program))
                        : $dp->cek_evidance,
                ]);
                ProgramApprovalLog::record($dp, ProgramApprovalLog::TRANSFER_APPROVED, actor: $this->linkActor($role),
                    note: "Transfer ke {$dp->bank} {$dp->norek} a.n. {$dp->namrek}");

                $this->pushDpToSpreadsheet($dp);

                // Estafet Otomatis: Tim SCM mentransfer ke rekening dealer
                $scmTransferNotification = $this->notifyScmTransfer($dp);
            } elseif ($action === 'transfer') {
                $validated = $request->validate([
                    'no_pembayaran' => ['required', 'string', 'max:255'],
                ], [
                    'no_pembayaran.required' => 'No. referensi transfer wajib diisi sebelum menyelesaikan transfer.',
                    'no_pembayaran.max' => 'No. referensi transfer maksimal 255 karakter.',
                ]);

                $dp->update([
                    'status_potong_purchase' => DataProgram::DONE_TRANSFER,
                    'status_potong_ar' => DataProgram::DONE_TRANSFER,
                    'tgl_potong_tf' => date('n/j/Y'),
                    'no_pembayaran' => trim($validated['no_pembayaran']),
                ]);
                ProgramApprovalLog::record($dp, ProgramApprovalLog::TRANSFERRED, actor: $this->linkActor($role), note: "No. Referensi: {$dp->no_pembayaran}");

                $this->pushDpToSpreadsheet($dp);

                // Estafet Otomatis: Tim Pajak mengecek PPh klaim yang sudah ditransfer
                $taxNotification = $this->notifyTaxTeam($dp);
            } elseif ($action === 'setuju') {
                $dp->update([
                    'status_potong_ar' => 'DEALER SETUJU (PROSES AR)',
                    'tf_status' => 'POTONG',
                ]);
                ProgramApprovalLog::record($dp, ProgramApprovalLog::APPROVED, actor: $this->linkActor($role));

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
                ProgramApprovalLog::record($dp, ProgramApprovalLog::AR_NOTIFIED, (bool) ($arNotification['success'] ?? false),
                    actor: ProgramApprovalLog::AUTOMATIC, recipient: ProgramApprovalLog::arRecipient($dp), note: $arNotification['message'] ?? null);

                // Kirim perubahan status_potong_ar ke Google Spreadsheet
                $this->pushDpToSpreadsheet($dp);
            } elseif ($action === 'potong') {
                $validated = $request->validate([
                    'no_pembayaran' => ['required', 'string', 'max:255'],
                ], [
                    'no_pembayaran.required' => 'No. Pembayaran wajib diisi sebelum menyelesaikan pemotongan.',
                    'no_pembayaran.max' => 'No. Pembayaran maksimal 255 karakter.',
                ]);

                $todayDate = date('n/j/Y');
                $dp->update([
                    'status_potong_purchase' => 'SUDAH POTONG',
                    'status_potong_ar' => 'DONE',
                    'tgl_potong_tf' => $todayDate,
                    'no_pembayaran' => trim($validated['no_pembayaran']),
                ]);
                ProgramApprovalLog::record($dp, ProgramApprovalLog::CUT, actor: $this->linkActor($role), note: "No. Pembayaran: {$dp->no_pembayaran}");

                // Kirim status SUDAH POTONG ke Google Spreadsheet
                $this->pushDpToSpreadsheet($dp);

                // Estafet Otomatis: Tim Pajak mengecek PPh klaim yang sudah dipotong
                $taxNotification = $this->notifyTaxTeam($dp);
            } elseif ($action === 'tunda') {
                $dp->update([
                    'status_potong_ar' => 'PENDING DEALER',
                ]);
                ProgramApprovalLog::record($dp, ProgramApprovalLog::POSTPONED, actor: $this->linkActor($role));

                // Kirim status PENDING DEALER ke Google Spreadsheet
                $this->pushDpToSpreadsheet($dp);
            } elseif ($action === 'cek_pajak') {
                $validated = $request->validate([
                    'cek_pajak_tarif' => ['required', 'string', 'max:100'],
                    'selisih' => ['nullable', 'string', 'max:30', 'regex:/^[\s\-()Rp\d.,]*$/i'],
                    'note_pph' => ['nullable', 'string', 'max:255'],
                ], [
                    'cek_pajak_tarif.required' => 'Cek Pajak wajib diisi.',
                    'cek_pajak_tarif.max' => 'Cek Pajak maksimal 100 karakter.',
                    'selisih.regex' => 'Selisih hanya boleh berisi angka, mis. 0 atau -1.500.',
                    'note_pph.max' => 'Note PPh maksimal 255 karakter.',
                ]);

                $dp->update([
                    'cek_pajak_tarif' => trim($validated['cek_pajak_tarif']),
                    'selisih' => $this->dpSyncService->parseAmount($validated['selisih'] ?? null),
                    'note_pph' => trim((string) ($validated['note_pph'] ?? '')) ?: null,
                    'tax_checked_at' => now(),
                ]);
                ProgramApprovalLog::record($dp, ProgramApprovalLog::TAX_CHECKED, actor: $this->linkActor($role),
                    note: "Cek Pajak: {$dp->cek_pajak_tarif} · Selisih: ".number_format((float) $dp->selisih, 0, ',', '.').' · Note PPh: '.($dp->note_pph ?: '-'));

                // Kirim Cek Pajak, Selisih & Note PPh ke Google Spreadsheet
                $this->pushDpToSpreadsheet($dp, withTaxCheck: true);

                // Estafet Otomatis: hasil cek pajak dikirim ke WA Tim SCM
                $scmNotification = $this->notifyScm($dp);
            }

            return view('data_programs.confirmed', [
                'dp' => $dp->fresh(),
                'action' => $action,
                'role' => $role,
                'arNotification' => $arNotification,
                'taxNotification' => $taxNotification,
                'scmNotification' => $scmNotification,
                'scmTransferNotification' => $scmTransferNotification,
            ]);
        }

        // GET request: Display landing page with action buttons
        return view('data_programs.confirm', [
            'dp' => $dp,
            'role' => $role,
            'stage' => $stage,
            'documents' => $this->documentsOf($dp),
        ]);
    }

    /**
     * The claim's uploaded documents for the confirmation page: a link to open each one, or why it is missing
     * (Faktur Pajak is not required for a non-PKP store).
     *
     * @return list<array{label: string, url: ?string, note: string}>
     */
    protected function documentsOf(DataProgram $dp): array
    {
        $link = fn (?string $value) => $this->reconciliationService->isValidUrl($value) ? trim($value) : null;

        return [
            ['label' => 'CN', 'url' => $link($dp->cn), 'note' => 'Belum ada'],
            ['label' => 'Agreement', 'url' => $link($dp->agrement), 'note' => 'Belum ada'],
            ['label' => 'Faktur Pajak', 'url' => $link($dp->cek_fp), 'note' => $this->reconciliationService->isPkp($dp) ? 'Belum ada' : 'Tidak wajib'],
            // KTP / NPWP pemilik rekening hanya untuk klaim yang ditransfer
            ...($dp->isTransfer() ? [['label' => 'KTP / NPWP', 'url' => $link($dp->cek_evidance), 'note' => 'Belum ada']] : []),
        ];
    }

    /**
     * Who acted through a confirmation link, for the approval history (the link itself has no login).
     */
    protected function linkActor(?string $role): string
    {
        return ProgramApprovalLog::LINK_ACTORS[$role] ?? 'Link WA';
    }

    /**
     * After AR cuts a claim, ask the area's tax team to check its PPh; a failed WhatsApp is recorded and can be sent
     * again from Riwayat Approval.
     *
     * @return array{success: bool, message: string}
     */
    protected function notifyTaxTeam(DataProgram $dp): array
    {
        try {
            $result = $this->waService->sendDataProgramTaxCheckNotification($dp);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        if ($result['success']) {
            $dp->forceFill(['tax_notified_at' => now()])->save();
        } else {
            Log::warning("Gagal kirim WA cek pajak untuk DataProgram ID {$dp->id}: {$result['message']}");
        }

        ProgramApprovalLog::record($dp, ProgramApprovalLog::TAX_NOTIFIED, $result['success'],
            actor: ProgramApprovalLog::AUTOMATIC, recipient: ProgramApprovalLog::teamRecipient($dp, TeamContact::TAX), note: $result['message']);

        return $result;
    }

    /**
     * After the dealer chose a bank transfer, ask the SCM team to transfer the claim; a failed WhatsApp is recorded and
     * can be sent again from Riwayat Approval.
     *
     * @return array{success: bool, message: string}
     */
    protected function notifyScmTransfer(DataProgram $dp): array
    {
        try {
            $result = $this->waService->sendDataProgramTransferRequestToScm($dp);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        if (! $result['success']) {
            Log::warning("Gagal kirim WA permintaan transfer ke SCM untuk DataProgram ID {$dp->id}: {$result['message']}");
        }

        ProgramApprovalLog::record($dp, ProgramApprovalLog::SCM_TRANSFER_NOTIFIED, $result['success'],
            actor: ProgramApprovalLog::AUTOMATIC, recipient: ProgramApprovalLog::teamRecipient($dp, TeamContact::SCM), note: $result['message']);

        return $result;
    }

    /**
     * After the tax team saves their check, send its result to the area's SCM team; a failed WhatsApp is recorded and
     * can be sent again from Riwayat Approval.
     *
     * @return array{success: bool, message: string}
     */
    protected function notifyScm(DataProgram $dp): array
    {
        try {
            $result = $this->waService->sendDataProgramScmNotification($dp);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        if (! $result['success']) {
            Log::warning("Gagal kirim WA hasil cek pajak ke SCM untuk DataProgram ID {$dp->id}: {$result['message']}");
        }

        ProgramApprovalLog::record($dp, ProgramApprovalLog::SCM_NOTIFIED, $result['success'],
            actor: ProgramApprovalLog::AUTOMATIC, recipient: ProgramApprovalLog::teamRecipient($dp, TeamContact::SCM), note: $result['message']);

        return $result;
    }

    /**
     * Push status update to Google Spreadsheet master (with the tax team's Cek Pajak, Selisih & Note PPh when asked).
     */
    protected function pushDpToSpreadsheet(DataProgram $dp, bool $withTaxCheck = false): void
    {
        try {
            $rowIndex = (int) str_replace('row_', '', (string) $dp->row_hash);
            $this->dpSyncService->pushUpdatesToSpreadsheet([[
                'row_index' => $rowIndex > 0 ? $rowIndex : null,
                'kode_bt' => $dp->kode_bt,
                'dealer_name' => $dp->dealer_name,
                'program' => $dp->program,
                'program_name' => $dp->program_name,
                'periode' => $dp->periode,
                'status_potong_purchase' => $dp->status_potong_purchase,
                'status_potong_ar' => $dp->status_potong_ar,
                'tgl_potong_tf' => $dp->tgl_potong_tf,
                'no_pembayaran' => $dp->no_pembayaran,
                'tf_status' => $dp->tf_status,
                'bank' => $dp->bank,
                'norek' => $dp->norek,
                'namrek' => $dp->namrek,
                'cek_dokumen' => $dp->cek_dokumen,
                'keterangan' => $dp->keterangan,
                'cn' => $dp->cn,
                'agrement' => $dp->agrement,
                'cek_fp' => $dp->cek_fp,
                'no_faktur_pajak' => $dp->no_faktur_pajak,
                'ket_faktur_pajak' => $dp->ket_faktur_pajak,
                'noted' => $dp->noted,
                ...($withTaxCheck ? [
                    'cek_pajak_tarif' => $dp->cek_pajak_tarif,
                    'selisih' => $dp->selisih,
                    'note_pph' => $dp->note_pph,
                ] : []),
            ]], null, $dp->areaEnum(), $dp->brand);
        } catch (\Throwable $e) {
            Log::warning("Gagal push status potong ke spreadsheet untuk DP ID {$dp->id}: {$e->getMessage()}");
        }
    }
}
