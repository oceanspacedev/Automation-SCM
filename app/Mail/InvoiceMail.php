<?php

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public ?string $senderEmail = null,
        public ?string $senderName = null,
        public ?string $customSubject = null,
        public ?string $customMessage = null,
        public array $ccEmails = []
    ) {}

    public function envelope(): Envelope
    {
        $from = null;
        if (! empty($this->senderEmail)) {
            $from = new Address($this->senderEmail, $this->senderName ?? config('mail.from.name', 'Rebate. MSI'));
        }

        $cc = [];
        foreach ($this->ccEmails as $ccEmail) {
            if (! empty($ccEmail) && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                $cc[] = new Address($ccEmail);
            }
        }

        return new Envelope(
            from: $from,
            to: array_filter([$this->invoice->email]),
            cc: $cc,
            subject: $this->customSubject ?: "Invoice {$this->invoice->invoice_number} - {$this->invoice->dealer_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'customMessage' => $this->customMessage,
                'senderName' => $this->senderName,
                'senderEmail' => $this->senderEmail,
            ],
        );
    }

    public function attachments(): array
    {
        $viewName = match ($this->invoice->invoice_type) {
            'DSA' => 'invoices.dsa',
            'REGULAR', 'REGULER' => 'invoices.regular',
            default => 'invoices.nps-fl',
        };

        $pdf = Pdf::loadView($viewName, ['invoice' => $this->invoice])
            ->setPaper('a4', 'portrait');

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                "{$this->invoice->invoice_number}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
