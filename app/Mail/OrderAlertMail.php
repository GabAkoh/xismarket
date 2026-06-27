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
 * Internal "new online order" alert for the store owner/admins (not the
 * customer — they get OrderReceiptMail). Best-effort, fired when a storefront
 * order is placed.
 */
class OrderAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $store = $this->order->tenant;
        $storeName = $store->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address(config('mail.from.address'), $storeName),
            subject: 'New order '.$this->order->number.' · '.$storeName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-alert',
            with: [
                'order' => $this->order,
                'store' => $this->order->tenant,
            ],
        );
    }
}
