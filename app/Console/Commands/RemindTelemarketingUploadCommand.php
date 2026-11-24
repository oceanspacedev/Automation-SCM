<?php

namespace App\Console\Commands;

use App\Models\AutomationSchedule;
use App\Models\Invoice;
use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('invoices:remind-telemarketing-upload
    {--days= : Berapa hari setelah invoice terbit dokumen belum di-upload (opsional, bawaan mengikuti Jadwal Otomatis)}
    {--limit=100 : Batas invoice yang diproses per jalan}')]
#[Description('Kirim pengingat WhatsApp ke telemarketing untuk invoice yang dokumennya belum di-upload ke Form Program')]
class RemindTelemarketingUploadCommand extends Command
{
    public function handle(WhatsAppService $waService): int
    {
        $schedule = AutomationSchedule::forKey(AutomationSchedule::INVOICE_REMIND_UPLOAD);

        $daysOption = $this->option('days');
        $everyDays = $daysOption !== null ? max(1, (int) $daysOption) : ($schedule?->reminder_every_days ?? 3);
        $maxReminders = $schedule?->reminder_max ?? 3;

        if ($everyDays < 1) {
            $result = 'Pengingat otomatis tidak aktif (disetel Tanpa Pengingat).';
            $this->info($result);
            $schedule?->recordRun($result);

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $cutoffDate = now()->subDays($everyDays);

        $invoices = Invoice::with('draft')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($cutoffDate) {
                $q->where('created_at', '<=', $cutoffDate)
                    ->where(function ($sq) use ($cutoffDate) {
                        $sq->whereNull('upload_reminded_at')
                            ->orWhere('upload_reminded_at', '<=', $cutoffDate);
                    });
            })
            ->where('upload_reminder_count', '<', $maxReminders)
            ->whereNotUploadedToForm()
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->filter(fn (Invoice $inv) => ! $inv->isUploadedToForm());

        if ($invoices->isEmpty()) {
            $result = 'Tidak ada invoice yang perlu diingatkan ke telemarketing.';
            $this->info($result);
            $schedule?->recordRun($result);

            return self::SUCCESS;
        }

        $groups = [];
        foreach ($invoices as $invoice) {
            $dealer = $invoice->dealer();
            $phone = $waService->formatPhone($dealer?->telemarketing_phone);
            if (! $phone) {
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

        if (empty($groups)) {
            $result = 'Tidak ada nomor WhatsApp telemarketing yang ditemukan untuk invoice yang belum di-upload.';
            $this->warn($result);
            $schedule?->recordRun($result);

            return self::SUCCESS;
        }

        $remindedCount = 0;
        $failedCount = 0;

        foreach ($groups as $group) {
            $uploadUrl = $group['invoices']->first()->documentUploadUrl();
            $text = $waService->buildRegionUploadReminderMessage($group['region'], $group['name'], $group['invoices'], $uploadUrl);
            $clientRef = 'REMIND-AUTO-UP-'.Str::slug($group['region']).'-'.now()->format('His');

            $res = $waService->sendTextMessage($group['phone'], $text, $clientRef);

            foreach ($group['invoices'] as $inv) {
                WhatsAppLog::create([
                    'invoice_id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'recipient_phone' => $group['phone'],
                    'status' => $res['success'] ? 'sent' : 'failed',
                    'provider_message_id' => $res['provider_id'] ?? null,
                    'error_message' => $res['success'] ? null : $res['message'],
                ]);

                if ($res['success']) {
                    $inv->update([
                        'upload_reminded_at' => now(),
                        'upload_reminder_count' => ($inv->upload_reminder_count ?? 0) + 1,
                    ]);
                }
            }

            if ($res['success']) {
                $remindedCount += $group['invoices']->count();
            } else {
                $failedCount += $group['invoices']->count();
            }
        }

        $parts = [];
        if ($remindedCount > 0) {
            $parts[] = "Pengingat terkirim ke telemarketing untuk {$remindedCount} invoice.";
        }
        if ($failedCount > 0) {
            $parts[] = "Gagal mengirim pengingat untuk {$failedCount} invoice.";
        }

        $result = implode(' ', $parts) ?: 'Tidak ada pesan WhatsApp yang berhasil dikirim.';
        $this->info($result);
        $schedule?->recordRun($result);

        return self::SUCCESS;
    }
}
