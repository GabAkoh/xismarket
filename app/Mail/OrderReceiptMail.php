<?php

namespace App\Mail;

use App\Models\Orders\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The order confirmation / receipt emailed to a customer. Sent synchronously
 * (best-effort) when an online order is placed, and on demand by staff.
 */
class OrderReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $store = $this->order->tenant;
        $storeName = $store->name ?? config('mail.from.name');

        $envelope = new Envelope(
            from: new Address(config('mail.from.address'), $storeName),
            subject: ($this->order->payment_status === 'paid' ? 'Receipt' : 'Order confirmation')
                .' · '.$this->order->number.' · '.$storeName,
        );

        // Let the customer reply straight to the store if it has an email.
        if (! empty($store?->email)) {
            $envelope->replyTo = [new Address($store->email, $storeName)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-receipt',
            with: [
                'order' => $this->order,
                'store' => $this->order->tenant,
            ],
        );
    }
}
