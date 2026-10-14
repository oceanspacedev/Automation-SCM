<?php

namespace App\Http\Controllers;

use App\Area;
use App\Models\Draft;
use App\Models\MasterProgram;
use App\Services\AreaContext;
use App\Services\DataProgramSyncService;
use App\Services\InvoiceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DraftController extends Controller
{
    public function __construct(
        protected InvoiceCalculator $calculator,
        protected DataProgramSyncService $dpSyncService
    ) {}

    /**
     * Get paginated draft list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Draft::query()->with(['invoice', 'dataProgram']);

        // Search query
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('dealer_code', 'like', "%{$search}%")
                    ->orWhere('dealer_name', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('cn_number', 'like', "%{$search}%")
                    ->orWhere('program_name', 'like', "%{$search}%");
            });
        }

        // Region filter
        if ($region = $request->input('region')) {
            $query->where('region', $region);
        }

        // RSM filter
        if ($rsm = $request->input('rsm')) {
            $query->where('rsm', $rsm);
        }

        // Dealer filter
        if ($dealer = $request->input('dealer')) {
            $query->where(function ($q) use ($dealer) {
                $q->where('dealer_code', $dealer)
                    ->orWhere('dealer_name', 'like', "%{$dealer}%");
            });
        }

        // Program filter
        if ($program = $request->input('program')) {
            $query->where('program_name', 'like', "%{$program}%");
        }

        // Invoice type filter (DSA / NPS FL)
        if ($type = $request->input('invoice_type')) {
            $query->where('invoice_type', $type);
        }

        // Status filter (ready, error, invoiced)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Date filter
        if ($date = $request->input('date')) {
            $query->where('invoice_date', $date);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $drafts = $query->orderByDesc('id')->paginate($perPage);

        return response()->json($drafts);
    }

    /**
     * Get draft details with calculation comparison.
     */
    public function show($id): JsonResponse
    {
        $draft = Draft::with('invoice')->findOrFail($id);
        $comparison = $this->calculator->compare($draft);

        return response()->json([
            'draft' => $draft,
            'comparison' => $comparison,
        ]);
    }

    /**
     * Re-validate calculation against business rules.
     */
    public function validateDraft($id): JsonResponse
    {
        $draft = Draft::findOrFail($id);
        $comparison = $this->calculator->compare($draft);

        $notes = $draft->validation_notes ?? [];
        $notes['comparison'] = $comparison['fields'];
        $notes['is_matched'] = $comparison['is_matched'];
        $notes['calculated'] = $comparison['calculated'];

        // If invoice_type is invalid, keep error
        $invType = strtoupper(trim((string) $draft->invoice_type));
        if (! in_array($invType, ['DSA', 'NPS FL', 'REGULAR', 'REGULER'], true)) {
            $draft->status = 'error';
        } elseif ($draft->status !== 'invoiced') {
            $draft->status = 'ready';
        }

        $draft->validation_notes = $notes;
        $draft->save();

        return response()->json([
            'message' => 'Validasi berhasil dijalankan.',
            'draft' => $draft->load('invoice'),
            'comparison' => $comparison,
        ]);
    }

    /**
     * Store a manually created draft invoice.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'area' => ['nullable', Rule::enum(Area::class)],
            'dealer_name' => ['required', 'string', 'max:255'],
            'dealer_code' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'kode_bt' => ['nullable', 'string', 'max:100'],
            'rsm' => ['nullable', 'string', 'max:100'],
            'real_qty' => ['nullable', 'numeric', 'min:0'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'npwp_name' => ['nullable', 'string', 'max:255'],
            'npwp_type' => ['nullable', 'string', 'max:50'],
            'pph_type' => ['nullable', 'string', 'max:50'],
            'support_amount' => ['required', 'numeric', 'min:0'],
            'program_name' => ['required', 'string', 'max:255'],
            'program_code' => ['nullable', 'string', 'max:100'],
            'program_period' => ['nullable', 'string', 'max:100'],
            // No CN dipakai sebagai nomor invoice
            'cn_number' => ['required', 'string', 'max:100', Rule::unique('drafts', 'cn_number'), Rule::unique('invoices', 'invoice_number')],
            'invoice_date' => ['nullable', 'date'],
            'invoice_type' => ['nullable', 'string', 'max:50'],
            'dpp' => ['nullable', 'numeric'],
            'dpp_lain' => ['nullable', 'numeric'],
            'ppn' => ['nullable', 'numeric'],
            'pph' => ['nullable', 'numeric'],
            'netpay' => ['nullable', 'numeric'],
            'address' => ['nullable', 'string'],
            'email' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'item_code' => ['nullable', 'string', 'max:100'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'ref_note' => ['nullable', 'string'],
        ], [
            'cn_number.required' => 'No CN wajib diisi karena dipakai sebagai nomor invoice.',
            'cn_number.unique' => 'No CN :input sudah dipakai draft atau invoice lain.',
        ]);

        $supportAmount = (float) $validated['support_amount'];
        $pphType = $validated['pph_type'] ?? 'BADAN';
        $npwpType = $validated['npwp_type'] ?? 'BADAN';
        $invoiceType = strtoupper(trim((string) ($validated['invoice_type'] ?? 'NPS FL')));
        if (! in_array($invoiceType, ['DSA', 'NPS FL', 'REGULAR'], true)) {
            $invoiceType = 'NPS FL';
        }

        // Draft masuk ke area halaman Draft yang sedang dibuka (default Jawa Barat)
        $draftArea = $validated['area'] ?? app(AreaContext::class)->resolve()->value;

        // Resolve program code (e.g. "PROGRAM 345") from Master Program when not supplied
        $validated['program_code'] = MasterProgram::normalizeCode($validated['program_code'] ?? null)
            ?? MasterProgram::where('name', trim($validated['program_name']))->value('code');

        // Calculate tax via InvoiceCalculator
        $calcResult = $this->calculator->calculateFromValues($supportAmount, $pphType, $npwpType);

        // Accept user-specified values if provided, otherwise fallback to calculator
        $dpp = array_key_exists('dpp', $validated) && $validated['dpp'] !== null ? (float) $validated['dpp'] : $calcResult['dpp'];
        $dppLain = array_key_exists('dpp_lain', $validated) && $validated['dpp_lain'] !== null ? (float) $validated['dpp_lain'] : $calcResult['dpp_lain'];
        $ppn = array_key_exists('ppn', $validated) && $validated['ppn'] !== null ? (float) $validated['ppn'] : $calcResult['ppn'];
        $pph = array_key_exists('pph', $validated) && $validated['pph'] !== null ? (float) $validated['pph'] : $calcResult['pph'];
        $netpay = array_key_exists('netpay', $validated) && $validated['netpay'] !== null ? (float) $validated['netpay'] : $calcResult['netpay'];

        $comparison = [
            'dpp' => ['excel' => $dpp, 'system' => $calcResult['dpp'], 'diff' => abs($dpp - $calcResult['dpp']), 'status' => abs($dpp - $calcResult['dpp']) <= 1 ? 'MATCH' : 'DIFFERENT'],
            'dpp_lain' => ['excel' => $dppLain, 'system' => $calcResult['dpp_lain'], 'diff' => abs($dppLain - $calcResult['dpp_lain']), 'status' => abs($dppLain - $calcResult['dpp_lain']) <= 1 ? 'MATCH' : 'DIFFERENT'],
            'ppn' => ['excel' => $ppn, 'system' => $calcResult['ppn'], 'diff' => abs($ppn - $calcResult['ppn']), 'status' => abs($ppn - $calcResult['ppn']) <= 1 ? 'MATCH' : 'DIFFERENT'],
            'pph' => ['excel' => $pph, 'system' => $calcResult['pph'], 'diff' => abs($pph - $calcResult['pph']), 'status' => abs($pph - $calcResult['pph']) <= 1 ? 'MATCH' : 'DIFFERENT'],
            'netpay' => ['excel' => $netpay, 'system' => $calcResult['netpay'], 'diff' => abs($netpay - $calcResult['netpay']), 'status' => abs($netpay - $calcResult['netpay']) <= 1 ? 'MATCH' : 'DIFFERENT'],
        ];

        // Sequential number
        $maxId = Draft::max('id') ?? 0;
        $no = (string) ($maxId + 1);

        $draftData = array_merge($validated, [
            'no' => $no,
            'area' => $draftArea,
            'real_qty' => $validated['real_qty'] ?? 1,
            'npwp_type' => $npwpType,
            'pph_type' => $pphType,
            'invoice_type' => $invoiceType,
            'invoice_date' => $validated['invoice_date'] ?? now()->toDateString(),
            'dpp' => $dpp,
            'dpp_lain' => $dppLain,
            'ppn' => $ppn,
            'pph' => $pph,
            'netpay' => $netpay,
            'status' => 'ready',
            'validation_notes' => [
                'is_matched' => true,
                'comparison' => $comparison,
                'calculated' => $calcResult,
                'errors' => [],
            ],
        ]);

        $draft = Draft::create($draftData);

        return response()->json([
            'success' => true,
            'message' => 'Draft invoice berhasil ditambahkan. Generate invoice terlebih dahulu agar data otomatis dikirim ke Data Program.',
            'data' => $draft->load(['invoice', 'dataProgram']),
        ], 201);
    }

    /**
     * Delete a draft invoice.
     */
    public function destroy($id): JsonResponse
    {
        $draft = Draft::with('invoice')->findOrFail($id);

        if ($draft->invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Draft tidak dapat dihapus karena sudah diterbitkan menjadi invoice.',
            ], 422);
        }

        $draft->delete();

        return response()->json([
            'success' => true,
            'message' => 'Draft invoice berhasil dihapus.',
        ]);
    }

    /**
     * Push a single draft to Data Program & Google Spreadsheet.
     */
    public function pushToDataProgram(int $id): JsonResponse
    {
        $draft = Draft::findOrFail($id);

        if ($draft->status !== 'invoiced') {
            return response()->json([
                'success' => false,
                'message' => 'Generate invoice terlebih dahulu sebelum mengirim draft ke Data Program.',
            ], 422);
        }

        $result = $this->dpSyncService->syncDraftToDataProgram($draft, true);

        return response()->json([
            'success' => true,
            'message' => 'Draft invoice berhasil dikirim ke Data Program & Google Spreadsheet.',
            'data_program' => $result['data_program'],
            'sheet_push' => $result['sheet_push'],
        ]);
    }

    /**
     * Push a batch of drafts to Data Program & Google Spreadsheet.
     */
    public function pushBatchToDataProgram(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:drafts,id'],
        ]);

        $drafts = Draft::whereIn('id', $validated['ids'])->where('status', 'invoiced')->get();

        if ($drafts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada draft terpilih yang sudah di-generate invoice-nya.',
            ], 422);
        }

        $syncedCount = 0;
        $dataPrograms = [];

        foreach ($drafts as $draft) {
            $res = $this->dpSyncService->syncDraftToDataProgram($draft, false);
            if ($res['success']) {
                $syncedCount++;
                $dataPrograms[] = $res['data_program'];
            }
        }

        $sheetPushResult = null;
        if (! empty($dataPrograms)) {
            try {
                $sheetPushResult = $this->dpSyncService->pushDraftsToSpreadsheet($dataPrograms);
            } catch (\Throwable $e) {
                Log::warning('Batch push drafts to spreadsheet failed: '.$e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'count' => $syncedCount,
            'message' => sprintf(
                'Berhasil mengirim %d draft ke Data Program%s.',
                $syncedCount,
                $sheetPushResult && ($sheetPushResult['success'] ?? false) ? ' dan Google Spreadsheet' : ''
            ),
            'sheet_push' => $sheetPushResult,
        ]);
    }
}
