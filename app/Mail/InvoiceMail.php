<?php

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_number} - {$this->invoice->dealer_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
        );
    }

    public function attachments(): array
    {
        $viewName = $this->invoice->invoice_type === 'DSA'
            ? 'invoices.dsa'
            : 'invoices.nps-fl';

        $pdf = Pdf::loadView($viewName, ['invoice' => $this->invoice])
            ->setPaper('a4', 'portrait');

        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $pdf->output(),
                "{$this->invoice->invoice_number}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
