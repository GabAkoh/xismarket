<?php

namespace App\Mail;

use App\Models\Storefront\Subscriber;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welcome email sent to a shopper when they join the storefront community.
 * Best-effort, sent on first signup only.
 */
class WelcomeSubscriberMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscriber $subscriber, public Tenant $store) {}

    public function envelope(): Envelope
    {
        $storeName = $this->store->name ?? config('mail.from.name');

        $envelope = new Envelope(
            from: new Address(config('mail.from.address'), $storeName),
            subject: 'Welcome to '.$storeName.'!',
        );

        if (! empty($this->store->email)) {
            $envelope->replyTo = [new Address($this->store->email, $storeName)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.welcome-subscriber',
            with: ['subscriber' => $this->subscriber, 'store' => $this->store],
        );
    }
}
