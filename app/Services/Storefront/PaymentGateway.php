<?php

namespace App\Services\Storefront;

use Illuminate\Support\Str;

/**
 * SANDBOX card payment gateway.
 *
 * This performs real input validation (Luhn, expiry, CVC, brand detection) and
 * simulates an authorisation, but does NOT contact a real processor and moves
 * no money. To go live, reimplement charge() against a provider (Stripe, PayPal,
 * Adyen, …) — the storefront only depends on the array shape returned here.
 *
 * Card data is validated and discarded; only a brand + last-4 + reference is
 * ever returned for storage. The full PAN/CVC are never persisted or logged.
 *
 * Test behaviour: any Luhn-valid card is approved EXCEPT numbers ending in
 * "0002", which simulate a decline (mirrors common test-card conventions).
 */
class PaymentGateway
{
    /**
     * @param  array{number?:string,name?:string,expiry?:string,cvc?:string}  $card
     * @return array{success:bool,reference?:string,last4?:string,brand?:string,message?:string}
     */
    public function charge(array $card, float $amount): array
    {
        $number = preg_replace('/\D/', '', $card['number'] ?? '');
        $cvc = preg_replace('/\D/', '', $card['cvc'] ?? '');
        $expiry = trim($card['expiry'] ?? '');

        if (strlen($number) < 13 || strlen($number) > 19 || ! $this->passesLuhn($number)) {
            return $this->decline('Your card number looks invalid.');
        }
        if (! $this->validExpiry($expiry)) {
            return $this->decline('Your card has expired or the expiry date is invalid.');
        }
        if (strlen($cvc) < 3 || strlen($cvc) > 4) {
            return $this->decline('Your security code (CVC) is invalid.');
        }
        if (round($amount, 2) <= 0) {
            return $this->decline('Invalid payment amount.');
        }

        // Simulated processor decline.
        if (str_ends_with($number, '0002')) {
            return $this->decline('Your card was declined. Please try another card.');
        }

        return [
            'success' => true,
            'reference' => 'CARD-'.strtoupper(Str::random(10)),
            'last4' => substr($number, -4),
            'brand' => $this->brand($number),
        ];
    }

    protected function decline(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    /** Luhn checksum. */
    protected function passesLuhn(string $number): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }

    /** Accepts MM/YY or MM/YYYY and rejects past/invalid dates. */
    protected function validExpiry(string $expiry): bool
    {
        if (! preg_match('/^(\d{1,2})\s*\/\s*(\d{2}|\d{4})$/', $expiry, $m)) {
            return false;
        }
        $month = (int) $m[1];
        $year = (int) $m[2];
        if ($year < 100) {
            $year += 2000;
        }
        if ($month < 1 || $month > 12) {
            return false;
        }

        // Valid through the end of the expiry month.
        $end = \Illuminate\Support\Carbon::create($year, $month, 1)->endOfMonth();

        return $end->isFuture();
    }

    /** Rough brand detection from the leading digits. */
    protected function brand(string $number): string
    {
        return match (true) {
            str_starts_with($number, '4') => 'Visa',
            (bool) preg_match('/^5[1-5]/', $number), (bool) preg_match('/^2[2-7]/', $number) => 'Mastercard',
            (bool) preg_match('/^3[47]/', $number) => 'Amex',
            str_starts_with($number, '6') => 'Discover',
            default => 'Card',
        };
    }
}
