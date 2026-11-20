<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceMail;
use App\Models\DataProgram;
use App\Models\Draft;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\GoogleToken;
use App\Models\Invoice;
use App\Models\ProgramSubmission;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceGenerator $generator,
        protected WhatsAppService $whatsAppService,
        protected GoogleMailService $googleMailService,
        protected DataProgramSyncService $dpSyncService
    ) {}

    /**
     * Delete an invoice, its associated Data Program record, and return draft status to ready.
     */
    public function destroy(int $id): JsonResponse
    {
        $record = Invoice::findOrFail($id);
        DB::transaction(function () use ($record): void {
            if ($record->draft_id) {
                DataProgram::withoutGlobalScopes()
                    ->where(function ($q) use ($record) {
                        $q->where('draft_id', $record->draft_id)
                            ->orWhere('row_hash', sprintf('draft_%d', $record->draft_id));
                        if (! empty($record->invoice_number)) {
                            $q->orWhere('cn', $record->invoice_number);
                        }
                    })
                    ->delete();
            } elseif (! empty($record->invoice_number)) {
                DataProgram::withoutGlobalScopes()->where('cn', $record->invoice_number)->delete();
            }

            $record->draft?->update(['status' => 'ready']);
            $record->delete();
        });

        return response()->json(['success' => true, 'message' => 'Invoice dan data program terkait berhasil dihapus.']);
    }

    /**
     * Delete multiple invoices, associated Data Program records, and return draft statuses to ready.
     */
    public function destroyBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:invoices,id'],
        ]);

        $count = count($validated['ids']);

        DB::transaction(function () use ($validated): void {
            $invoices = Invoice::whereIn('id', $validated['ids'])->get();
            $draftIds = [];
            $rowHashes = [];
            $cnNumbers = [];

            foreach ($invoices as $inv) {
                if ($inv->draft_id) {
                    $draftIds[] = $inv->draft_id;
                    $rowHashes[] = sprintf('draft_%d', $inv->draft_id);
                }
                if (! empty($inv->invoice_number)) {
                    $cnNumbers[] = $inv->invoice_number;
                }
            }

            if (! empty($draftIds) || ! empty($cnNumbers)) {
                DataProgram::withoutGlobalScopes()
                    ->where(function ($q) use ($draftIds, $rowHashes, $cnNumbers) {
                        $hasCondition = false;
                        if (! empty($draftIds)) {
                            $q->whereIn('draft_id', $draftIds);
                            $hasCondition = true;
                        }
                        if (! empty($rowHashes)) {
                            $hasCondition ? $q->orWhereIn('row_hash', $rowHashes) : $q->whereIn('row_hash', $rowHashes);
                            $hasCondition = true;
                        }
                        if (! empty($cnNumbers)) {
                            $hasCondition ? $q->orWhereIn('cn', $cnNumbers) : $q->whereIn('cn', $cnNumbers);
                        }
                    })
                    ->delete();
            }

            if (! empty($draftIds)) {
                Draft::withoutGlobalScopes()->whereIn('id', $draftIds)->update(['status' => 'ready']);
            }

            Invoice::whereIn('id', $validated['ids'])->delete();
        });

        return response()->json([
            'success' => true,
            'message' => "Berhasil menghapus {$count} invoice beserta data di Data Program.",
            'deleted_count' => $count,
        ]);
    }

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

        if ($uploadStatus = $request->input('upload_status')) {
            if ($uploadStatus === 'uploaded' || $uploadStatus === 'yes') {
                $query->whereUploadedToForm();
            } elseif ($uploadStatus === 'unuploaded' || $uploadStatus === 'no') {
                $query->whereNotUploadedToForm();
            }
        }

        if ($date = $request->input('date')) {
            $query->where('invoice_date', $date);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $invoices = $query->orderByDesc('id')->paginate($perPage);

        $collection = $invoices->getCollection();
        if ($collection->isNotEmpty()) {
            $kodeBts = $collection->map(fn (Invoice $i) => $i->dealer_code ?: $i->draft?->kode_bt)->filter()->unique()->all();
            $programNames = $collection->pluck('program_name')->filter()->unique()->all();
            $invNumbers = $collection->pluck('invoice_number')->filter()->unique()->all();
            $draftIds = $collection->pluck('draft_id')->filter()->unique()->all();

            $uploadedSubmissions = ProgramSubmission::withoutGlobalScopes(['area', 'brand'])
                ->whereIn('id_real', $kodeBts)
                ->whereIn('program_name', $programNames)
                ->where(fn ($q) => $q->whereNotNull('credit_note_url')->orWhereNotNull('agreement_url'))
                ->get(['id_real', 'program_name'])
                ->mapWithKeys(fn ($s) => ["{$s->id_real}|{$s->program_name}" => true])
                ->all();

            $uploadedDpCns = DataProgram::withoutGlobalScopes(['area', 'brand'])
                ->where(function ($q) use ($invNumbers, $draftIds) {
                    $q->whereIn('cn', $invNumbers);
                    if (! empty($draftIds)) {
                        $q->orWhereIn('draft_id', $draftIds);
                    }
                })
                ->where(function ($q) {
                    $q->where(fn ($sub) => $sub->where('cek_dokumen', '!=', 'BELUM MATCH TEST')->whereNotNull('cek_dokumen'))
                        ->orWhere(fn ($sub) => $sub->whereNotNull('cn')->where('cn', 'like', 'http%'))
                        ->orWhere(fn ($sub) => $sub->whereNotNull('agrement')->where('agrement', 'like', 'http%'));
                })
                ->pluck('cn')
                ->flip()
                ->all();

            $collection->each(function (Invoice $inv) use ($uploadedSubmissions, $uploadedDpCns) {
                $kodeBt = $inv->dealer_code ?: $inv->draft?->kode_bt;
                $key = "{$kodeBt}|{$inv->program_name}";
                $isUploaded = isset($uploadedSubmissions[$key]) || isset($uploadedDpCns[$inv->invoice_number]);
                $inv->setAttribute('is_uploaded_to_form', $isUploaded);
            });
        }

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
        $wasAlreadySent = (bool) ($invoice->email_sent_at || $invoice->whatsapp_sent_at);

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

            $actionText = $wasAlreadySent ? 'berhasil dikirim ulang ke' : 'berhasil dikirim ke';
            $msg = "Invoice {$invoice->invoice_number} {$actionText} ".implode(' & ', $sentChannels).'.';
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

        $resend = $request->boolean('resend', false);
        $invoices = Invoice::with('draft')->whereIn('id', $ids)->orderBy('id')->get();

        if ($invoices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice yang dipilih tidak ditemukan.',
            ], 422);
        }

        $hasUnsent = $invoices->contains(fn (Invoice $inv) => $inv->status !== 'sent' || ! $inv->email_sent_at || ! $inv->whatsapp_sent_at);
        $effectiveResend = $resend || ! $hasUnsent;

        return $this->sendInvoices($invoices, $this->resolveSender($request->input('sender_id')), $regionSender, resend: $effectiveResend);
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

        return $this->sendInvoices($invoices, $this->resolveSender($request->input('sender_id')), $regionSender, resend: false);
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
     * sent for an invoice is skipped unless $resend is true.
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    private function sendInvoices(Collection $invoices, EmailAccount $sender, InvoiceRegionSender $regionSender, bool $resend = false): JsonResponse
    {
        $updates = [];
        $emailFailed = [];

        $emailInvoices = $resend ? $invoices : $invoices->whereNull('email_sent_at');
        foreach ($emailInvoices as $invoice) {
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

        $waInvoices = $resend ? $invoices->values() : $invoices->whereNull('whatsapp_sent_at')->values();
        $whatsApp = $regionSender->send($waInvoices);

        foreach (array_keys($whatsApp['sent']) as $invoiceId) {
            $updates[$invoiceId] = [...($updates[$invoiceId] ?? []), 'whatsapp_sent_at' => now()];
        }

        foreach ($invoices->whereIn('id', array_keys($updates)) as $invoice) {
            $invoice->update([...$updates[$invoice->id], 'status' => 'sent']);
        }

        $files = collect($whatsApp['files']);
        $emailed = count(array_filter($updates, fn (array $update) => isset($update['email_sent_at'])));

        $statusWord = $resend ? 'terkirim / dikirim ulang' : 'terkirim';
        $message = array_filter([
            'Pengiriman selesai: '.count($updates)." dari {$invoices->count()} invoice {$statusWord}.",
            $emailed > 0 ? "Email ke dealer: {$emailed} {$statusWord}." : null,
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
     * Send WhatsApp upload reminder to telemarketing for a single invoice.
     */
    public function remindUpload(Request $request, $id): JsonResponse
    {
        $invoice = Invoice::with('draft')->findOrFail($id);

        $result = $this->whatsAppService->sendInvoiceUploadReminderToTelemarketing($invoice, $request->input('phone'));

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Pengingat upload dokumen untuk invoice {$invoice->invoice_number} berhasil dikirim ke WhatsApp Telemarketing.",
            'provider_id' => $result['provider_id'] ?? null,
        ]);
    }

    /**
     * Send batch WhatsApp upload reminder to telemarketing per region for selected invoices.
     */
    public function remindUploadBatch(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || ! is_array($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih setidaknya satu invoice untuk diingatkan.',
            ], 422);
        }

        $invoices = Invoice::with('draft')->whereIn('id', $ids)->get();
        if ($invoices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice tidak ditemukan.',
            ], 422);
        }

        $groups = [];
        $withoutPhone = [];

        foreach ($invoices as $invoice) {
            $dealer = $invoice->dealer();
            $phone = $this->whatsAppService->formatPhone($dealer?->telemarketing_phone);

            if (! $phone) {
                $withoutPhone[] = $invoice->invoice_number;

                continue;
            }

            $region = strtoupper(trim((string) ($invoice->draft?->region ?: $dealer->region))) ?: 'TANPA REGION';
            $key = "{$region}|{$phone}";
            $groups[$key] ??= [
                'region' => $region,
                'phone' => $phone,
                'name' => trim((string) $dealer->telemarketing) ?: null,
                'invoices' => collect(),
            ];
            $groups[$key]['invoices']->push($invoice);
        }

        $sentCount = 0;
        $failedCount = 0;
        $fileResults = [];

        foreach ($groups as $group) {
            $uploadUrl = $group['invoices']->first()->documentUploadUrl();
            $text = $this->whatsAppService->buildRegionUploadReminderMessage($group['region'], $group['name'], $group['invoices'], $uploadUrl);
            $clientRef = 'REMIND-UP-REG-'.Str::slug($group['region']).'-'.now()->format('His');

            $res = $this->whatsAppService->sendTextMessage($group['phone'], $text, $clientRef);

            foreach ($group['invoices'] as $inv) {
                WhatsAppLog::create([
                    'invoice_id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'recipient_phone' => $group['phone'],
                    'status' => $res['success'] ? 'sent' : 'failed',
                    'provider_message_id' => $res['provider_id'] ?? null,
                    'error_message' => $res['success'] ? null : $res['message'],
                ]);
            }

            if ($res['success']) {
                $sentCount += $group['invoices']->count();
            } else {
                $failedCount += $group['invoices']->count();
            }

            $fileResults[] = [
                'region' => $group['region'],
                'phone' => $group['phone'],
                'count' => $group['invoices']->count(),
                'success' => $res['success'],
                'message' => $res['message'],
            ];
        }

        $parts = [];
        if ($sentCount > 0) {
            $parts[] = "Pengingat berhasil dikirim untuk {$sentCount} invoice.";
        }
        if ($failedCount > 0) {
            $parts[] = "Gagal mengirim pengingat untuk {$failedCount} invoice.";
        }
        if (! empty($withoutPhone)) {
            $parts[] = 'Nomor WA telemarketing belum ada di Master Dealer untuk: '.implode(', ', array_slice($withoutPhone, 0, 5)).(count($withoutPhone) > 5 ? '…' : '').'.';
        }

        return response()->json([
            'success' => $sentCount > 0,
            'message' => implode(' ', $parts),
            'sent' => $sentCount,
            'failed' => $failedCount,
            'results' => $fileResults,
        ], $sentCount > 0 ? 200 : 422);
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
}
