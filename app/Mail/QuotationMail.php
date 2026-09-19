<?php

namespace App\Mail;

use App\Models\Quotation;
use App\Support\QuotationDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  string|null  $host  the host the quotation was sent from, kept so
     *                             the queued attachment prints the right
     *                             company identity
     */
    public function __construct(public Quotation $quotation, public ?string $host = null)
    {
        $this->host ??= request()->getHost();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Quotation {$this->quotation->number}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.quotation',
            with: ['quotation' => $this->quotation],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = QuotationDocument::render($this->quotation, (string) $this->host);

        return [
            Attachment::fromData(fn (): string => $pdf->output(), "{$this->quotation->number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
