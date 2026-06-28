<?php

namespace App\Services\Storefront;

use App\Mail\NewArrivalsMail;
use App\Models\Storefront\Subscriber;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Queues a "new arrivals" email to every active (not-unsubscribed) subscriber
 * of a store. Shared by the manual broadcast and the weekly digest. Each email
 * is queued (sent by the worker) and carries the subscriber's unsubscribe link.
 */
class NewArrivalsBroadcaster
{
    /**
     * @param  Collection<int, \App\Models\Inventory\Product>  $products
     * @return int  number of emails queued
     */
    public function send(Tenant $store, Collection $products, ?string $intro = null, ?string $subject = null): int
    {
        if ($products->isEmpty()) {
            return 0;
        }

        $count = 0;
        Subscriber::active()->chunkById(200, function ($subscribers) use ($store, $products, $intro, $subject, &$count) {
            foreach ($subscribers as $subscriber) {
                Mail::to($subscriber->email)->queue(
                    new NewArrivalsMail($subscriber, $store, $products, $intro, $subject)
                );
                $count++;
            }
        });

        return $count;
    }
}
