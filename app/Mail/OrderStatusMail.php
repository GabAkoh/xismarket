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
 * Notifies the customer when their order advances to a new status
 * (confirmed, preparing, ready, dispatched, delivered, completed, cancelled).
 */
class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $status) {}

    public function envelope(): Envelope
    {
        $store = $this->order->tenant;

        return new Envelope(
            from: new Address(config('mail.from.address'), $store->name ?? config('mail.from.name')),
            subject: 'Order '.$this->order->number.': '.$this->headline(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-status',
            with: [
                'order' => $this->order,
                'store' => $this->order->tenant,
                'headline' => $this->headline(),
                'message' => $this->message(),
            ],
        );
    }

    /** Short, friendly label for the status. */
    public function headline(): string
    {
        return match ($this->status) {
            'confirmed' => 'Order confirmed',
            'preparing' => 'Order is being prepared',
            'ready' => $this->order->fulfillment_type === 'pickup' ? 'Ready for pickup' : 'Order is ready',
            'dispatched' => 'Out for delivery',
            'delivered' => 'Delivered',
            'completed' => 'Order complete',
            'cancelled' => 'Order cancelled',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    /** A sentence explaining the new status. */
    public function message(): string
    {
        return match ($this->status) {
            'confirmed' => "We've confirmed your order and will begin preparing it shortly.",
            'preparing' => 'Your order is now being prepared.',
            'ready' => $this->order->fulfillment_type === 'pickup'
                ? 'Your order is ready and waiting for collection.'
                : 'Your order is ready and will be dispatched soon.',
            'dispatched' => 'Your order is on its way to you.',
            'delivered' => 'Your order has been delivered. We hope you enjoy it!',
            'completed' => 'Your order is complete. Thank you for shopping with us!',
            'cancelled' => 'Your order has been cancelled. If this is unexpected, please get in touch.',
            default => 'Your order status has been updated to '.str_replace('_', ' ', $this->status).'.',
        };
    }
}
