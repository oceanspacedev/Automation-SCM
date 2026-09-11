<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceMail;
use App\Models\Draft;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Services\InvoiceGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    protected InvoiceGenerator $generator;

    public function __construct(InvoiceGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Paginated invoice list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with('draft');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('dealer_code', 'like', "%{$search}%")
                  ->orWhere('dealer_name', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('cn_number', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('invoice_type')) {
            $query->where('invoice_type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($date = $request->input('date')) {
            $query->where('invoice_date', $date);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $invoices = $query->orderByDesc('id')->paginate($perPage);

        return response()->json($invoices);
    }

    /**
     * Invoice detail.
     */
    public function show($id): JsonResponse
    {
        $invoice = Invoice::with('draft')->findOrFail($id);

        return response()->json($invoice);
    }

    /**
     * Generate invoice from Draft.
     */
    public function generate(Request $request, $draftId): JsonResponse
    {
        $draft = Draft::findOrFail($draftId);

        try {
            $invoice = $this->generator->generate($draft);

            return response()->json([
                'success' => true,
                'message' => "Invoice {$invoice->invoice_number} berhasil dibuat.",
                'invoice' => $invoice,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate invoices for all ready drafts (bulk generate).
     */
    public function generateAll(Request $request): JsonResponse
    {
        set_time_limit(300);

        $query = Draft::where('status', 'ready');

        if ($request->has('ids') && is_array($request->input('ids')) && count($request->input('ids')) > 0) {
            $query->whereIn('id', $request->input('ids'));
        }

        $drafts = $query->get();

        if ($drafts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada draft dengan status Ready untuk diterbitkan.',
                'generated_count' => 0,
            ], 422);
        }

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($drafts as $draft) {
            try {
                $this->generator->generate($draft);
                $successCount++;
            } catch (Exception $e) {
                $failedCount++;
                $errors[] = "Draft #{$draft->id} ({$draft->dealer_name}): {$e->getMessage()}";
            }
        }

        $message = "Berhasil menerbitkan {$successCount} invoice.";
        if ($failedCount > 0) {
            $message .= " {$failedCount} draft gagal diproses.";
        }

        return response()->json([
            'success' => $successCount > 0,
            'message' => $message,
            'generated_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * HTML Preview of the invoice (using exact same Blade PDF template).
     */
    public function preview($id)
    {
        $invoice = Invoice::with('draft')->findOrFail($id);
        $viewName = $invoice->invoice_type === 'DSA' ? 'invoices.dsa' : 'invoices.nps-fl';

        return view($viewName, ['invoice' => $invoice]);
    }

    /**
     * Download or stream PDF.
     */
    public function downloadPdf($id)
    {
        $invoice = Invoice::with('draft')->findOrFail($id);

        if ($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path)) {
            return Storage::disk('public')->download(
                $invoice->pdf_path,
                "{$invoice->invoice_number}.pdf"
            );
        }

        // Re-generate if not found
        $viewName = $invoice->invoice_type === 'DSA' ? 'invoices.dsa' : 'invoices.nps-fl';
        $pdf = Pdf::loadView($viewName, ['invoice' => $invoice])
            ->setPaper('a4', 'portrait');

        return $pdf->download("{$invoice->invoice_number}.pdf");
    }

    /**
     * Send invoice via email.
     */
    public function sendEmail(Request $request, $id): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $invoice = Invoice::with('draft')->findOrFail($id);

        try {
            Mail::to($request->input('email'))->send(new InvoiceMail($invoice));

            EmailLog::create([
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'recipient_email'=> $request->input('email'),
                'status'         => 'sent',
            ]);

            return response()->json([
                'success' => true,
                'message' => "Invoice {$invoice->invoice_number} berhasil dikirim ke {$request->input('email')}.",
            ]);
        } catch (Exception $e) {
            EmailLog::create([
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'recipient_email'=> $request->input('email'),
                'status'         => 'failed',
                'error_message'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Email sending history, grouped by date.
     */
    public function emailLogs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $logs = EmailLog::with('invoice:id,invoice_number,dealer_name,invoice_type')
            ->when($request->input('date'), fn ($q, $d) => $q->whereDate('created_at', $d))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('invoice_number', 'like', "%{$s}%")
                        ->orWhere('recipient_email', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($logs);
    }

    /**
     * Dashboard metrics.
     */
    public function dashboard(): JsonResponse
    {
        $totalDraft = Draft::count();
        $draftReady = Draft::where('status', 'ready')->count();
        $draftError = Draft::where('status', 'error')->count();

        $totalInvoice = Invoice::count();
        $invoiceDsa = Invoice::where('invoice_type', 'DSA')->count();
        $invoiceNpsFl = Invoice::where('invoice_type', 'NPS FL')->count();
        $totalNetpay = (float) Invoice::sum('netpay');

        return response()->json([
            'total_draft' => $totalDraft,
            'draft_ready' => $draftReady,
            'draft_error' => $draftError,
            'total_invoice' => $totalInvoice,
            'invoice_dsa' => $invoiceDsa,
            'invoice_nps_fl' => $invoiceNpsFl,
            'total_netpay' => $totalNetpay,
        ]);
    }
}
