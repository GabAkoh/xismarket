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
use Illuminate\Support\Collection;

/**
 * A "new arrivals" email to a storefront subscriber — used by both the manual
 * broadcast and the weekly digest. Carries the subscriber so the template can
 * render their one-click unsubscribe link. Queued (sent via the worker).
 */
class NewArrivalsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\Inventory\Product>  $products
     */
    public function __construct(
        public Subscriber $subscriber,
        public Tenant $store,
        public Collection $products,
        public ?string $intro = null,
        public ?string $subjectLine = null,
    ) {}

    public function envelope(): Envelope
    {
        $storeName = $this->store->name ?? config('mail.from.name');

        $envelope = new Envelope(
            from: new Address(config('mail.from.address'), $storeName),
            subject: $this->subjectLine ?: ('New arrivals at '.$storeName),
        );

        if (! empty($this->store->email)) {
            $envelope->replyTo = [new Address($this->store->email, $storeName)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new-arrivals',
            with: [
                'subscriber' => $this->subscriber,
                'store' => $this->store,
                'products' => $this->products,
                'intro' => $this->intro,
            ],
        );
    }
}
