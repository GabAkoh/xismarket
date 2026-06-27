<?php

namespace App\Services\Orders;

use App\Contracts\SmsSender;
use App\Mail\OrderAlertMail;
use App\Models\Orders\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Alerts the store (owner + admins) that a new online order has arrived — by
 * email now, and by SMS once an SMS provider is configured. Every send is
 * best-effort: a failure is reported but never breaks order placement.
 */
class OrderAlertService
{
    public function __construct(protected SmsSender $sms) {}

    /** Email + SMS the store about a freshly placed online order. */
    public function notifyNewOrder(Order $order): void
    {
        $order->loadMissing('items', 'customer', 'tenant');
        $store = $order->tenant;
        if (! $store) {
            return;
        }

        foreach ($this->recipientEmails($store) as $email) {
            try {
                Mail::to($email)->send(new OrderAlertMail($order));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $message = $this->smsMessage($order, $store);
        foreach ($this->recipientPhones($store) as $phone) {
            try {
                $this->sms->send($phone, $message);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Email recipients. The list configured in storefront settings wins; when
     * it's empty we fall back to the store contact plus active owner accounts.
     * Always deduped & validated.
     */
    protected function recipientEmails(Tenant $store): array
    {
        $configured = array_filter(
            (array) $store->setting('storefront.order_alert_emails', []),
            fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL),
        );
        if (! empty($configured)) {
            return array_values(array_unique($configured));
        }

        $owners = User::query()
            ->where('tenant_id', $store->id)
            ->where('is_owner', true)
            ->where('is_active', true)
            ->pluck('email')
            ->all();

        $all = array_merge([$store->email], $owners);

        return array_values(array_unique(array_filter(
            $all,
            fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL),
        )));
    }

    /**
     * Phone numbers to text. The list configured in storefront settings wins;
     * when it's empty we fall back to the store's contact phone.
     *
     * @return array<int,string>
     */
    protected function recipientPhones(Tenant $store): array
    {
        $configured = array_values(array_filter(array_map(
            fn ($p) => trim((string) $p),
            (array) $store->setting('storefront.order_alert_phones', []),
        )));
        if (! empty($configured)) {
            return array_values(array_unique($configured));
        }

        $phone = trim((string) ($store->phone ?? ''));

        return $phone === '' ? [] : [$phone];
    }

    /** Short SMS body summarising the order. */
    protected function smsMessage(Order $order, Tenant $store): string
    {
        $symbol = $store->currencySymbol();
        $who = $order->contact_name ?: 'Customer';

        return ($store->name ?: 'Store').': new order '.$order->number
            .' — '.$symbol.' '.number_format((float) $order->total, 2)
            .' ('.$order->fulfillment_type.') from '.$who.'.';
    }
}
