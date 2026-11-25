<?php

namespace App\Http\Controllers;

use App\Models\ArContact;
use App\Models\DataProgram;
use App\Models\ProgramApprovalLog;
use App\Models\TeamContact;
use App\Services\DataProgramSyncService;
use App\Services\ProgramApprovalFlow;
use App\Services\WhatsAppService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Riwayat Approval: where each Data Program row of the selected brand & area is in the approval flow to
 * telemarketing and AR, with every recorded step.
 */
class ProgramApprovalController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const EVENT_LABELS = [
        ProgramApprovalLog::TELEMARKETING_NOTIFIED => 'Notifikasi WhatsApp ke Telemarketing',
        ProgramApprovalLog::TELEMARKETING_REMINDED => 'Pengingat WhatsApp ke Telemarketing',
        ProgramApprovalLog::APPROVED => 'Dealer setuju dipotong',
        ProgramApprovalLog::TRANSFER_APPROVED => 'Dealer setuju ditransfer',
        ProgramApprovalLog::POSTPONED => 'Dealer menunda',
        ProgramApprovalLog::AR_NOTIFIED => 'Notifikasi WhatsApp ke AR',
        ProgramApprovalLog::CUT => 'Dipotong oleh AR',
        ProgramApprovalLog::SCM_CUT_NOTIFIED => 'Info sudah potong ke Tim SCM',
        ProgramApprovalLog::SCM_TRANSFER_NOTIFIED => 'Notifikasi WhatsApp ke Tim SCM (transfer)',
        ProgramApprovalLog::TRANSFERRED => 'Ditransfer oleh Tim SCM',
        ProgramApprovalLog::TAX_NOTIFIED => 'Notifikasi WhatsApp ke Tim Pajak',
        ProgramApprovalLog::TAX_CHECKED => 'Dicek Tim Pajak',
        ProgramApprovalLog::AP_NOTIFIED => 'Notifikasi WhatsApp ke Tim AP (hasil cek pajak)',
        ProgramApprovalLog::SCM_NOTIFIED => 'Notifikasi WhatsApp ke Tim SCM (hasil cek pajak)',
        ProgramApprovalLog::ROLLED_BACK => 'Tahap approval dikembalikan',
    ];

    public function __construct(
        private ProgramApprovalFlow $flow,
        private DataProgramSyncService $syncService,
        private WhatsAppService $waService,
    ) {}

    /**
     * Rows in the flow (filtered by stage & search) with their steps, and how many rows are at each stage.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'stage' => ['nullable', Rule::in(['all', ...array_keys(ProgramApprovalFlow::STAGES)])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) $request->input('search'));
        $rowsInFlow = fn (): Builder => $this->flow->inFlow(DataProgram::query())
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('dealer_name', 'like', "%{$search}%")
                ->orWhere('kode_bt', 'like', "%{$search}%")
                ->orWhere('program', 'like', "%{$search}%")
                ->orWhere('program_name', 'like', "%{$search}%")
                ->orWhere('telemarketing', 'like', "%{$search}%")));

        $rows = $this->flow->whereStage($rowsInFlow(), (string) $request->input('stage', 'all'))
            ->with('approvalLogs')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 25));
        $arName = $this->arNameResolver();
        $teamNames = $this->teamNamesResolver();

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (DataProgram $row) => $this->present($row, $arName($row), $teamNames($row)))->all(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'stages' => ProgramApprovalFlow::STAGES,
            'counts' => ['all' => $rowsInFlow()->count()]
                + collect(ProgramApprovalFlow::STAGES)->map(fn (string $label, string $stage) => $this->flow->whereStage($rowsInFlow(), $stage)->count())->all(),
        ]);
    }

    /**
     * One row's flow with every recorded step, oldest first.
     */
    public function show(int $id): JsonResponse
    {
        $row = DataProgram::query()->with('approvalLogs')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->present($row, ($this->arNameResolver())($row), ($this->teamNamesResolver())($row)) + [
                'timeline' => $row->approvalLogs->map(fn (ProgramApprovalLog $log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'label' => self::EVENT_LABELS[$log->event] ?? $log->event,
                    'success' => $log->success,
                    'actor' => $log->actor,
                    'recipient' => $log->recipient,
                    'note' => $log->note,
                    'at' => $log->created_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * The AR of a row in Master AR, picked like the WhatsApp to AR: the AR of the row's area.
     *
     * @return Closure(DataProgram): ?string
     */
    private function arNameResolver(): Closure
    {
        $names = ArContact::withoutGlobalScope('area')
            ->where('is_active', true)
            ->pluck('name', 'area');

        return fn (DataProgram $row): ?string => $names->get($row->area);
    }

    /**
     * Names of the active tax, SCM, and AP team members in Master Tim Pajak, SCM & AP (the same for every area), read once.
     *
     * @return Closure(DataProgram): array{tax: ?string, scm: ?string, ap: ?string}
     */
    private function teamNamesResolver(): Closure
    {
        $names = null;

        return function (DataProgram $row) use (&$names): array {
            return $names ??= [
                'tax' => TeamContact::namesOf(TeamContact::TAX),
                'scm' => TeamContact::namesOf(TeamContact::SCM),
                'ap' => TeamContact::namesOf(TeamContact::AP),
            ];
        };
    }

    /**
     * @param  array{tax: ?string, scm: ?string, ap: ?string}  $teamNames
     * @return array<string, mixed>
     */
    private function present(DataProgram $row, ?string $arName, array $teamNames): array
    {
        $stage = $this->flow->stage($row);

        return [
            'id' => $row->id,
            'dealer_name' => $row->dealer_name,
            'kode_bt' => $row->kode_bt,
            'program' => $row->program,
            'program_name' => $row->program_name,
            'periode' => $row->periode,
            'region' => $row->region,
            'telemarketing' => $row->telemarketing,
            'ar' => $arName,
            'net_pay' => $row->net_pay,
            'status_potong_purchase' => $row->status_potong_purchase,
            'status_potong_ar' => $row->status_potong_ar,
            'stage' => $stage,
            'stage_label' => ProgramApprovalFlow::STAGES[$stage],
            // Cara klaim diselesaikan: dipotong di order (AR) atau ditransfer (Tim SCM)
            'settlement' => in_array($stage, ['cut', 'tax_checked'], true) ? ($row->isTransfer() ? 'Done transfer' : 'Sudah potong') : null,
            'steps' => $this->flow->steps($row, $row->approvalLogs, $arName, $teamNames['tax'], $teamNames['scm'], $teamNames['ap']),
        ];
    }

    /**
     * Roll back a row's approval to an earlier stage (e.g. from tax check back to AR, or postponed/telemarketing).
     */
    public function rollback(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'target_stage' => ['required', Rule::in(['approved', 'postponed', 'waiting_telemarketing', 'awaiting_transfer'])],
            'reason' => ['required', 'string', 'max:500'],
            'notify_ar' => ['nullable', 'boolean'],
            'notify_scm' => ['nullable', 'boolean'],
        ], [
            'target_stage.required' => 'Tahap tujuan wajib dipilih.',
            'target_stage.in' => 'Tahap tujuan tidak valid.',
            'reason.required' => 'Alasan pengembalian tahap wajib diisi.',
            'reason.max' => 'Alasan pengembalian maksimal 500 karakter.',
        ]);

        $row = DataProgram::query()->with('approvalLogs')->findOrFail($id);
        $oldStage = $this->flow->stage($row);
        $targetStage = $validated['target_stage'];
        $reason = trim($validated['reason']);
        $actor = $request->user()?->name ?: ($request->user()?->email ?: 'User');

        $stageLabels = [
            'approved' => 'Menunggu AR',
            'postponed' => 'Ditunda Dealer (Pending)',
            'waiting_telemarketing' => 'Awal (Belum ke Telemarketing)',
            'awaiting_transfer' => 'Menunggu Transfer SCM',
        ];
        $targetLabel = $stageLabels[$targetStage] ?? $targetStage;

        match ($targetStage) {
            'approved' => $row->fill([
                'status_potong_purchase' => 'BISA DI POTONG',
                'status_potong_ar' => 'DEALER SETUJU (PROSES AR)',
                'tf_status' => 'POTONG',
                'no_pembayaran' => null,
                'tgl_potong_tf' => null,
                'tax_checked_at' => null,
                'cek_pajak_tarif' => null,
                'selisih' => 0,
                'note_pph' => null,
                'tax_notified_at' => null,
                'scm_notified_at' => null,
            ]),
            'awaiting_transfer' => $row->fill([
                'status_potong_purchase' => 'BISA DI POTONG',
                'status_potong_ar' => DataProgram::TRANSFER_APPROVED,
                'tf_status' => 'TF',
                'no_pembayaran' => null,
                'tgl_potong_tf' => null,
                'tax_checked_at' => null,
                'cek_pajak_tarif' => null,
                'selisih' => 0,
                'note_pph' => null,
                'tax_notified_at' => null,
                'scm_notified_at' => null,
            ]),
            'postponed' => $row->fill([
                'status_potong_purchase' => 'BISA DI POTONG',
                'status_potong_ar' => 'PENDING DEALER',
                'no_pembayaran' => null,
                'tgl_potong_tf' => null,
                'tax_checked_at' => null,
                'cek_pajak_tarif' => null,
                'selisih' => 0,
                'note_pph' => null,
                'tax_notified_at' => null,
                'scm_notified_at' => null,
            ]),
            'waiting_telemarketing' => $row->fill([
                'status_potong_purchase' => 'BISA DI POTONG',
                'status_potong_ar' => 'BELUM POTONG',
                'telemarketing_notified_at' => null,
                'telemarketing_reminder_count' => 0,
                'no_pembayaran' => null,
                'tgl_potong_tf' => null,
                'tax_checked_at' => null,
                'cek_pajak_tarif' => null,
                'selisih' => 0,
                'note_pph' => null,
                'tax_notified_at' => null,
                'scm_notified_at' => null,
            ]),
        };

        $row->save();

        $oldStageLabel = ProgramApprovalFlow::STAGES[$oldStage] ?? $oldStage;
        $logNote = "Dikembalikan dari tahap '{$oldStageLabel}' ke '{$targetLabel}'. Alasan: {$reason}";

        ProgramApprovalLog::record(
            $row,
            ProgramApprovalLog::ROLLED_BACK,
            actor: $actor,
            note: $logNote
        );

        $this->pushDpToSpreadsheet($row, withTaxCheck: true);

        if (! empty($validated['notify_ar']) && $targetStage === 'approved') {
            try {
                $this->waService->sendDataProgramClaimNotificationToAr($row);
            } catch (\Throwable $e) {
                Log::warning("Gagal kirim ulang WA ke AR saat rollback DataProgram ID {$row->id}: ".$e->getMessage());
            }
        }

        if (! empty($validated['notify_scm']) && $targetStage === 'awaiting_transfer') {
            try {
                $this->waService->sendDataProgramTransferRequestToScm($row);
            } catch (\Throwable $e) {
                Log::warning("Gagal kirim WA ke Tim SCM saat rollback DataProgram ID {$row->id}: ".$e->getMessage());
            }
        }

        $row->refresh()->load('approvalLogs');
        $arName = $this->arNameResolver();
        $teamNames = $this->teamNamesResolver();

        return response()->json([
            'success' => true,
            'message' => "Tahap approval berhasil dikembalikan ke {$targetLabel}.",
            'data' => $this->present($row, $arName($row), $teamNames($row)) + [
                'timeline' => $row->approvalLogs->map(fn (ProgramApprovalLog $log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'label' => self::EVENT_LABELS[$log->event] ?? $log->event,
                    'success' => $log->success,
                    'actor' => $log->actor,
                    'recipient' => $log->recipient,
                    'note' => $log->note,
                    'at' => $log->created_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * Push updated row to Google Spreadsheet master.
     */
    protected function pushDpToSpreadsheet(DataProgram $dp, bool $withTaxCheck = false): void
    {
        try {
            $rowIndex = (int) str_replace('row_', '', (string) $dp->row_hash);
            $this->syncService->pushUpdatesToSpreadsheet([[
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
            Log::warning("Gagal push rollback DataProgram ID {$dp->id} ke Google Spreadsheet: ".$e->getMessage());
        }
    }
}
