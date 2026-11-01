<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceMail;
use App\Models\Draft;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\GoogleToken;
use App\Models\Invoice;
use App\Models\WhatsAppLog;
use App\Services\CustomerLookupService;
use App\Services\DataProgramSyncService;
use App\Services\GoogleMailService;
use App\Services\InvoiceGenerator;
use App\Services\InvoiceRegionSender;
use App\Services\WhatsAppService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceGenerator $generator,
        protected WhatsAppService $whatsAppService,
        protected GoogleMailService $googleMailService,
        protected DataProgramSyncService $dpSyncService
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
     * Get list of active sender email accounts for dropdown.
     */
    public function emailAccounts(): JsonResponse
    {
        $defaultList = [
            [
                'name' => 'Rebate. MSI',
                'email' => 'ade@mediaselulerindonesia.com',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Program CS',
                'email' => 'admin.scm@completeselular.com',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Program MSI',
                'email' => 'admin.scm@mediaselulerindonesia.com',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Program SMI',
                'email' => 'admin.scm@satumediaindonesia.com',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Program Top',
                'email' => 'admin.scm@topselular.com',
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        try {
            if (Schema::hasTable('email_accounts')) {
                // Auto-seed default accounts if table is empty (e.g. fresh production)
                if (EmailAccount::count() === 0) {
                    foreach ($defaultList as $acc) {
                        EmailAccount::create($acc);
                    }
                }

                // If Google account is connected, ensure it exists in email_accounts
                $googleToken = GoogleToken::latest()->first();
                if ($googleToken && ! empty($googleToken->account_email)) {
                    $existing = EmailAccount::where('email', $googleToken->account_email)->first();
                    if ($existing) {
                        if (! $existing->is_active) {
                            $existing->update(['is_active' => true]);
                        }
                    } else {
                        EmailAccount::create([
                            'name' => 'Google Workspace',
                            'email' => $googleToken->account_email,
                            'is_default' => false,
                            'is_active' => true,
                        ]);
                    }
                }

                $accounts = EmailAccount::where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->get(['id', 'name', 'email', 'is_default']);

                if ($accounts->isNotEmpty()) {
                    return response()->json([
                        'data' => $accounts,
                    ]);
                }
            }
        } catch (\Throwable) {
            // Fallback gracefully below
        }

        $fallback = array_map(function ($acc, $idx) {
            return [
                'id' => $idx + 1,
                'name' => $acc['name'],
                'email' => $acc['email'],
                'is_default' => $acc['is_default'],
            ];
        }, $defaultList, array_keys($defaultList));

        return response()->json([
            'data' => $fallback,
        ]);
    }

    /**
     * Resolve active email sender account.
     */
    protected function resolveSender(?int $senderId = null): EmailAccount
    {
        if ($senderId) {
            $sender = EmailAccount::where('id', $senderId)->where('is_active', true)->first();
            if ($sender) {
                return $sender;
            }
        }

        $defaultSender = EmailAccount::where('is_default', true)->where('is_active', true)->first();
        if ($defaultSender) {
            return $defaultSender;
        }

        $activeSender = EmailAccount::where('is_active', true)->first();
        if ($activeSender) {
            return $activeSender;
        }

        return new EmailAccount([
            'id' => 0,
            'name' => 'Rebate. MSI',
            'email' => 'ade@mediaselularindonesia.com',
            'is_default' => true,
            'is_active' => true,
        ]);
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
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $dpResult = null;
        try {
            $dpResult = $this->dpSyncService->syncDraftToDataProgram($draft->refresh(), true);
        } catch (\Throwable $e) {
            Log::warning('Auto sync invoiced draft to data program failed: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "Invoice {$invoice->invoice_number} berhasil dibuat.".($dpResult ? ' Data otomatis dikirim ke Data Program.' : ' Namun gagal mengirim ke Data Program.'),
            'invoice' => $invoice,
            'data_program_sync' => $dpResult,
        ]);
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
        $dataPrograms = [];

        foreach ($drafts as $draft) {
            try {
                $this->generator->generate($draft, $billTo);
                $successCount++;
            } catch (Exception $e) {
                $failedCount++;
                $errors[] = "Draft #{$draft->id} ({$draft->dealer_name}): {$e->getMessage()}";

                continue;
            }

            try {
                $dataPrograms[] = $this->dpSyncService->syncDraftToDataProgram($draft->refresh(), false)['data_program'];
            } catch (\Throwable $e) {
                Log::warning("Auto sync invoiced draft #{$draft->id} to data program failed: ".$e->getMessage());
            }
        }

        if (! empty($dataPrograms)) {
            try {
                $this->dpSyncService->pushDraftsToSpreadsheet($dataPrograms);
            } catch (\Throwable $e) {
                Log::warning('Batch push invoiced drafts to spreadsheet failed: '.$e->getMessage());
            }
        }

        $message = "Berhasil menerbitkan {$successCount} invoice.";
        if (! empty($dataPrograms)) {
            $message .= ' '.count($dataPrograms).' data otomatis dikirim ke Data Program.';
        }
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

        // Validate sender if sender_id provided
        if ($request->has('sender_id')) {
            $senderCheck = EmailAccount::where('id', $request->input('sender_id'))->where('is_active', true)->first();
            if (! $senderCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun pengirim tidak valid atau tidak aktif.',
                ], 422);
            }
            $sender = $senderCheck;
        } else {
            $sender = $this->resolveSender();
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
                $subject = $request->input('subject');
                $customMessage = $request->input('message');
                $cc = (array) $request->input('cc', []);

                if ($this->googleMailService->isConnected()) {
                    $this->googleMailService->sendInvoiceMail(
                        invoice: $invoice,
                        sender: $sender,
                        to: $email,
                        cc: $cc,
                        subject: $subject,
                        customMessage: $customMessage
                    );
                } else {
                    Mail::to($email)->send(new InvoiceMail(
                        invoice: $invoice,
                        senderEmail: $sender->email,
                        senderName: $sender->name,
                        customSubject: $subject,
                        customMessage: $customMessage,
                        ccEmails: $cc
                    ));
                }

                $updates['email_sent_at'] = now();
                $updates['email'] = $email;

                EmailLog::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'sender_email' => $sender->email,
                    'sender_name' => $sender->name,
                    'recipient_email' => $email,
                    'status' => 'sent',
                ]);

                $sentChannels[] = "Email ({$email}) via {$sender->name}";
            } catch (Exception $e) {
                EmailLog::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'sender_email' => $sender->email,
                    'sender_name' => $sender->name,
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
     * Send the selected invoices: email to each dealer, WhatsApp to telemarketing as one PDF per region.
     */
    public function sendBatch(Request $request, InvoiceRegionSender $regionSender): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || ! is_array($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih setidaknya satu invoice untuk dikirim.',
            ], 422);
        }

        $invoices = $this->unsentInvoices()->whereIn('id', $ids)->get();

        if ($invoices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Semua invoice yang dipilih sudah pernah dikirim sebelumnya.',
            ], 422);
        }

        return $this->sendInvoices($invoices, $this->resolveSender($request->input('sender_id')), $regionSender);
    }

    /**
     * Send every unsent invoice: email to each dealer, WhatsApp to telemarketing as one PDF per region.
     */
    public function quickSendAll(Request $request, InvoiceRegionSender $regionSender): JsonResponse
    {
        $invoices = $this->unsentInvoices()->get();

        if ($invoices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada invoice yang belum dikirim.',
            ], 422);
        }

        return $this->sendInvoices($invoices, $this->resolveSender($request->input('sender_id')), $regionSender);
    }

    /**
     * Invoices not sent yet (no email or no WhatsApp sent so far), in the order they were generated.
     *
     * @return Builder<Invoice>
     */
    private function unsentInvoices(): Builder
    {
        return Invoice::with('draft')
            ->where(fn (Builder $q) => $q->whereNull('email_sent_at')->orWhereNull('whatsapp_sent_at'))
            ->where('status', '!=', 'sent')
            ->orderBy('id');
    }

    /**
     * Email each invoice to its dealer (the draft's Email), and WhatsApp the invoices to telemarketing: the invoices of
     * one region with the same telemarketing number go together as one PDF, one invoice per page. A channel already
     * sent for an invoice is skipped.
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    private function sendInvoices(Collection $invoices, EmailAccount $sender, InvoiceRegionSender $regionSender): JsonResponse
    {
        $updates = [];
        $emailFailed = [];

        foreach ($invoices->whereNull('email_sent_at') as $invoice) {
            $email = $invoice->email ?: $invoice->draft?->email;

            if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if ($this->emailInvoice($invoice, $sender, $email)) {
                $updates[$invoice->id] = ['email_sent_at' => now(), 'email' => $email];
            } else {
                $emailFailed[] = $invoice->invoice_number;
            }
        }

        $whatsApp = $regionSender->send($invoices->whereNull('whatsapp_sent_at')->values());

        foreach (array_keys($whatsApp['sent']) as $invoiceId) {
            $updates[$invoiceId] = [...($updates[$invoiceId] ?? []), 'whatsapp_sent_at' => now()];
        }

        foreach ($invoices->whereIn('id', array_keys($updates)) as $invoice) {
            $invoice->update([...$updates[$invoice->id], 'status' => 'sent']);
        }

        $files = collect($whatsApp['files']);
        $emailed = count(array_filter($updates, fn (array $update) => isset($update['email_sent_at'])));

        $message = array_filter([
            'Pengiriman selesai: '.count($updates)." dari {$invoices->count()} invoice terkirim.",
            $emailed > 0 ? "Email ke dealer: {$emailed} terkirim." : null,
            $emailFailed ? 'Email gagal: '.$this->invoiceNumberList($emailFailed).'.' : null,
            $files->where('success', true)->isNotEmpty()
                ? 'WhatsApp ke telemarketing: '.$files->where('success', true)->map(fn (array $file) => "Region {$file['region']} ({$file['count']} invoice, 1 PDF)")->implode(', ').'.'
                : null,
            ...$files->where('success', false)->map(fn (array $file) => "WhatsApp Region {$file['region']} gagal: {$file['message']}")->all(),
            $whatsApp['without_phone']
                ? 'Nomor WA telemarketing belum ada di Master Dealer untuk invoice: '.$this->invoiceNumberList($whatsApp['without_phone']).'.'
                : null,
        ]);

        return response()->json([
            'success' => $updates !== [],
            'message' => implode(' ', $message),
            'sent' => count($updates),
            'failed' => $invoices->count() - count($updates),
            'whatsapp_files' => $whatsApp['files'],
        ], $updates !== [] ? 200 : 422);
    }

    /**
     * Email an invoice to its dealer (via Gmail API when connected), recorded in the email history.
     */
    private function emailInvoice(Invoice $invoice, EmailAccount $sender, string $email): bool
    {
        try {
            if ($this->googleMailService->isConnected()) {
                $this->googleMailService->sendInvoiceMail(invoice: $invoice, sender: $sender, to: $email);
            } else {
                Mail::to($email)->send(new InvoiceMail(invoice: $invoice, senderEmail: $sender->email, senderName: $sender->name));
            }

            $error = null;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        EmailLog::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'sender_email' => $sender->email,
            'sender_name' => $sender->name,
            'recipient_email' => $email,
            'status' => $error === null ? 'sent' : 'failed',
            'error_message' => $error,
        ]);

        return $error === null;
    }

    /**
     * A short list of invoice numbers for a message: the first five, then how many more.
     *
     * @param  list<string>  $invoiceNumbers
     */
    private function invoiceNumberList(array $invoiceNumbers): string
    {
        $more = count($invoiceNumbers) - 5;

        return implode(', ', array_slice($invoiceNumbers, 0, 5)).($more > 0 ? " dan {$more} lainnya" : '');
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
