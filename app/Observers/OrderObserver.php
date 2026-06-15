<?php

namespace App\Observers;

use App\Mail\OrderStatusMail;
use App\Models\Orders\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderObserver
{
    /** Statuses worth emailing the customer about (everything past the initial 'pending'). */
    protected const NOTIFY = ['confirmed', 'preparing', 'ready', 'dispatched', 'delivered', 'completed', 'cancelled'];

    /**
     * Email the customer whenever an order's status actually changes. Covers
     * every path (admin status update, fulfilment, cancellation, and the
     * Delivery module's dispatch/deliver syncing). Best-effort and deferred
     * until the surrounding DB transaction commits.
     */
    public function updated(Order $order): void
    {
        // Skip console runs (seeders, migrations, tinker) — only notify on real
        // staff actions during a web request.
        if (app()->runningInConsole()) {
            return;
        }

        if (! $order->wasChanged('status') || ! in_array($order->status, self::NOTIFY, true)) {
            return;
        }

        $email = $order->customer?->email;
        if (! $email) {
            return;
        }

        $status = $order->status;

        DB::afterCommit(function () use ($order, $email, $status) {
            try {
                Mail::to($email)->send(new OrderStatusMail($order->loadMissing('items'), $status));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
