<?php

namespace App\Http\Controllers;

use App\Models\ArContact;
use App\Models\DataProgram;
use App\Models\ProgramApprovalLog;
use App\Services\ProgramApprovalFlow;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ProgramApprovalLog::TELEMARKETING_NOTIFIED => 'WA ke telemarketing',
        ProgramApprovalLog::TELEMARKETING_REMINDED => 'Pengingat ke telemarketing',
        ProgramApprovalLog::APPROVED => 'Dealer setuju dipotong',
        ProgramApprovalLog::POSTPONED => 'Dealer menunda',
        ProgramApprovalLog::AR_NOTIFIED => 'WA ke AR',
        ProgramApprovalLog::CUT => 'Sudah dipotong AR',
    ];

    public function __construct(private ProgramApprovalFlow $flow) {}

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

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (DataProgram $row) => $this->present($row, $arName($row)))->all(),
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
            'data' => $this->present($row, ($this->arNameResolver())($row)) + [
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
     * The AR of a row in Master AR, picked like the WhatsApp to AR: its region first, then its big region.
     *
     * @return Closure(DataProgram): ?string
     */
    private function arNameResolver(): Closure
    {
        $contacts = ArContact::withoutGlobalScope('area')
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (ArContact $contact) => $contact->area.'|'.strtoupper(trim((string) $contact->region)));

        return fn (DataProgram $row): ?string => collect([$row->region, $row->big_region])
            ->map(fn (?string $region) => $contacts->get($row->area.'|'.strtoupper(trim((string) $region))))
            ->filter()
            ->first()?->name;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DataProgram $row, ?string $arName): array
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
            'steps' => $this->flow->steps($row, $row->approvalLogs, $arName),
        ];
    }
}
