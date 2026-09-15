<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceMail;
use App\Models\Draft;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\WhatsAppLog;
use App\Services\CustomerLookupService;
use App\Services\InvoiceGenerator;
use App\Services\WhatsAppService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceGenerator $generator,
        protected WhatsAppService $whatsAppService
    ) {}

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
     * Get list of standard Bill To options.
     */
    public function billToOptions(): JsonResponse
    {
        return response()->json(CustomerLookupService::getBillToOptions());
    }

    /**
     * Generate invoice from Draft.
     */
    public function generate(Request $request, $draftId): JsonResponse
    {
        $draft = Draft::findOrFail($draftId);
        $billTo = $request->input('bill_to');

        try {
            $invoice = $this->generator->generate($draft, $billTo);

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
        $billTo = $request->input('bill_to');

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
                $this->generator->generate($draft, $billTo);
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
        $viewName = match ($invoice->invoice_type) {
            'DSA' => 'invoices.dsa',
            'REGULAR', 'REGULER' => 'invoices.regular',
            default => 'invoices.nps-fl',
        };

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
        $viewName = match ($invoice->invoice_type) {
            'DSA' => 'invoices.dsa',
            'REGULAR', 'REGULER' => 'invoices.regular',
            default => 'invoices.nps-fl',
        };
        $pdf = Pdf::loadView($viewName, ['invoice' => $invoice])
            ->setPaper('a4', 'portrait');

        return $pdf->download("{$invoice->invoice_number}.pdf");
    }

    /**
     * Send invoice notification via Email and/or WhatsApp.
     */
    public function sendEmail(Request $request, $id): JsonResponse
    {
        $invoice = Invoice::with('draft')->findOrFail($id);

        $hasSentAll = ($invoice->email_sent_at && $invoice->whatsapp_sent_at);
        if ($invoice->status === 'sent' && $hasSentAll && ! $request->has('email') && ! $request->has('whatsapp')) {
            return response()->json([
                'success' => false,
                'message' => "Invoice {$invoice->invoice_number} sudah pernah dikirim lengkap ke Email dan WhatsApp.",
            ], 422);
        }

        $email = $request->input('email') ?: ($invoice->email ?? $invoice->draft?->email);
        $rawWhatsapp = $request->input('whatsapp') ?: ($invoice->whatsapp ?? $invoice->draft?->whatsapp);
        $whatsapp = $this->whatsAppService->formatPhone($rawWhatsapp);

        $hasValidEmail = ! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        $hasValidWhatsapp = ! empty($whatsapp);

        if (! $hasValidEmail && ! $hasValidWhatsapp) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat email atau nomor WhatsApp yang valid belum tersedia.',
            ], 422);
        }

        $sentChannels = [];
        $failedChannels = [];
        $updates = [];

        // 1. Send Email if recipient email is available
        if ($hasValidEmail) {
            try {
                Mail::to($email)->send(new InvoiceMail($invoice));

                $updates['email_sent_at'] = now();
                $updates['email'] = $email;

                EmailLog::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'recipient_email' => $email,
                    'status' => 'sent',
                ]);

                $sentChannels[] = "Email ({$email})";
            } catch (Exception $e) {
                EmailLog::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'recipient_email' => $email,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                $failedChannels[] = 'Email ('.$e->getMessage().')';
            }
        }

        // 2. Send WhatsApp if recipient phone is available
        if ($hasValidWhatsapp) {
            $waResult = $this->whatsAppService->sendInvoiceMessage($invoice, $whatsapp);
            if ($waResult['success']) {
                $updates['whatsapp_sent_at'] = now();
                $updates['whatsapp'] = $whatsapp;
                $sentChannels[] = "WhatsApp ({$whatsapp})";
            } else {
                $failedChannels[] = 'WhatsApp ('.$waResult['message'].')';
            }
        }

        if (! empty($sentChannels)) {
            $updates['status'] = 'sent';
            $invoice->update($updates);

            $msg = "Invoice {$invoice->invoice_number} berhasil dikirim ke ".implode(' & ', $sentChannels).'.';
            if (! empty($failedChannels)) {
                $msg .= ' (Gagal pada: '.implode(', ', $failedChannels).')';
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
                'sent_channels' => $sentChannels,
                'failed_channels' => $failedChannels,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengirim invoice: '.implode('; ', $failedChannels),
        ], 500);
    }

    /**
     * Quick-send invoice to the email and WhatsApp stored in the invoice (from draft).
     * No user input required.
     */
    public function quickSendEmail($id): JsonResponse
    {
        return $this->sendEmail(new Request, $id);
    }

    /**
     * Send multiple selected invoices via email and WhatsApp.
     */
    public function sendBatch(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || ! is_array($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih setidaknya satu invoice untuk dikirim.',
            ], 422);
        }

        $invoices = Invoice::with('draft')
            ->whereIn('id', $ids)
            ->where(function ($q) {
                $q->whereNull('email_sent_at')
                    ->orWhereNull('whatsapp_sent_at');
            })
            ->where('status', '!=', 'sent')
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Semua invoice yang dipilih sudah pernah dikirim sebelumnya.',
            ], 422);
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($invoices as $invoice) {
            $email = $invoice->email ?? $invoice->draft?->email;
            $rawWhatsapp = $invoice->whatsapp ?? $invoice->draft?->whatsapp;
            $whatsapp = $this->whatsAppService->formatPhone($rawWhatsapp);

            $hasEmail = ! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
            $hasWhatsapp = ! empty($whatsapp);

            if (! $hasEmail && ! $hasWhatsapp) {
                $failedCount++;

                continue;
            }

            $sentAny = false;
            $updates = [];

            if ($hasEmail && ! $invoice->email_sent_at) {
                try {
                    Mail::to($email)->send(new InvoiceMail($invoice));

                    $updates['email_sent_at'] = now();
                    $updates['email'] = $email;

                    EmailLog::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'recipient_email' => $email,
                        'status' => 'sent',
                    ]);

                    $sentAny = true;
                } catch (Exception $e) {
                    EmailLog::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'recipient_email' => $email,
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            if ($hasWhatsapp && ! $invoice->whatsapp_sent_at) {
                $waResult = $this->whatsAppService->sendInvoiceMessage($invoice, $whatsapp);
                if ($waResult['success']) {
                    $updates['whatsapp_sent_at'] = now();
                    $updates['whatsapp'] = $whatsapp;
                    $sentAny = true;
                }
            }

            if ($sentAny) {
                $updates['status'] = 'sent';
                $invoice->update($updates);
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Pengiriman selesai: {$successCount} invoice berhasil dikirim".($failedCount > 0 ? ", {$failedCount} gagal." : '.'),
            'sent' => $successCount,
            'failed' => $failedCount,
        ]);
    }

    /**
     * Batch send all unsent invoices that have an email or WhatsApp number.
     */
    public function quickSendAll(): JsonResponse
    {
        $invoices = Invoice::with('draft')
            ->where(function ($q) {
                $q->whereNull('email_sent_at')
                    ->orWhereNull('whatsapp_sent_at');
            })
            ->where('status', '!=', 'sent')
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('email')->where('email', '!=', '');
                })->orWhere(function ($sub) {
                    $sub->whereNotNull('whatsapp')->where('whatsapp', '!=', '');
                })->orWhereHas('draft', fn ($sq) => $sq->where(function ($sub2) {
                    $sub2->whereNotNull('email')->where('email', '!=', '')
                        ->orWhereNotNull('whatsapp')->where('whatsapp', '!=', '');
                }));
            })
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada invoice yang belum dikirim dengan data email atau WhatsApp tersedia.',
            ], 422);
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($invoices as $invoice) {
            $email = $invoice->email ?? $invoice->draft?->email;
            $rawWhatsapp = $invoice->whatsapp ?? $invoice->draft?->whatsapp;
            $whatsapp = $this->whatsAppService->formatPhone($rawWhatsapp);

            $hasEmail = ! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
            $hasWhatsapp = ! empty($whatsapp);

            if (! $hasEmail && ! $hasWhatsapp) {
                continue;
            }

            $sentAny = false;
            $updates = [];

            if ($hasEmail && ! $invoice->email_sent_at) {
                try {
                    Mail::to($email)->send(new InvoiceMail($invoice));

                    $updates['email_sent_at'] = now();
                    $updates['email'] = $email;

                    EmailLog::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'recipient_email' => $email,
                        'status' => 'sent',
                    ]);

                    $sentAny = true;
                } catch (Exception $e) {
                    EmailLog::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'recipient_email' => $email,
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            if ($hasWhatsapp && ! $invoice->whatsapp_sent_at) {
                $waResult = $this->whatsAppService->sendInvoiceMessage($invoice, $whatsapp);
                if ($waResult['success']) {
                    $updates['whatsapp_sent_at'] = now();
                    $updates['whatsapp'] = $whatsapp;
                    $sentAny = true;
                }
            }

            if ($sentAny) {
                $updates['status'] = 'sent';
                $invoice->update($updates);
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Proses pengiriman selesai: {$successCount} terkirim, {$failedCount} gagal.",
            'sent' => $successCount,
            'failed' => $failedCount,
        ]);
    }

    /**
     * WhatsApp sending history.
     */
    public function whatsAppLogs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $logs = WhatsAppLog::with('invoice:id,invoice_number,dealer_name,invoice_type')
            ->when($request->input('date'), fn ($q, $d) => $q->whereDate('created_at', $d))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('invoice_number', 'like', "%{$s}%")
                        ->orWhere('recipient_phone', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($logs);
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
        $invoiceRegular = Invoice::whereIn('invoice_type', ['REGULAR', 'REGULER'])->count();
        $totalNetpay = (float) Invoice::sum('netpay');

        return response()->json([
            'total_draft' => $totalDraft,
            'draft_ready' => $draftReady,
            'draft_error' => $draftError,
            'total_invoice' => $totalInvoice,
            'invoice_dsa' => $invoiceDsa,
            'invoice_nps_fl' => $invoiceNpsFl,
            'invoice_regular' => $invoiceRegular,
            'total_netpay' => $totalNetpay,
        ]);
    }
}
