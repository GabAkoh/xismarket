<?php

namespace App\Mail;

use App\Models\Pos\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A POS sale receipt emailed to the customer on demand (cashier action from the
 * receipt screen — optional, never automatic).
 */
class SaleReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Sale $sale) {}

    public function envelope(): Envelope
    {
        $store = $this->sale->tenant;
        $storeName = $store->name ?? config('mail.from.name');

        $envelope = new Envelope(
            from: new Address(config('mail.from.address'), $storeName),
            subject: 'Receipt · '.$this->sale->number.' · '.$storeName,
        );

        if (! empty($store?->email)) {
            $envelope->replyTo = [new Address($store->email, $storeName)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.sale-receipt',
            with: [
                'sale' => $this->sale,
                'store' => $this->sale->tenant,
            ],
        );
    }
}
