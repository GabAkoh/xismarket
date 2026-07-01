<?php

namespace App\Services\Storefront;

use App\Support\Tenancy;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Paystack payment gateway (NGN). Uses the standard redirect flow:
 *   initialize() → send the customer to the returned authorization_url →
 *   Paystack collects the card on its own secure page → verify() on return,
 *   and the webhook signature check confirms it server-to-server.
 *
 * No card data ever touches this app. Amounts are in Naira here and converted
 * to kobo (×100) for Paystack.
 */
class PaystackGateway
{
    public function configured(): bool
    {
        if (blank($this->secret())) {
            return false;
        }

        // Admin can toggle it off in settings; when unset (env-only mode) a key
        // present means enabled.
        $enabled = app(Tenancy::class)->current()?->setting('payments.enabled');

        return $enabled === null ? true : (bool) $enabled;
    }

    public function publicKey(): ?string
    {
        $fromSettings = app(Tenancy::class)->current()?->setting('payments.paystack_public');

        return filled($fromSettings) ? $fromSettings : config('services.paystack.public');
    }

    /** Secret key: tenant settings (encrypted) first, then env/config. */
    protected function secret(): ?string
    {
        $enc = app(Tenancy::class)->current()?->setting('payments.paystack_secret');
        if (filled($enc)) {
            try {
                return Crypt::decryptString($enc);
            } catch (\Throwable $e) {
                // Corrupt/rotated app key — fall back to config.
            }
        }

        return config('services.paystack.secret');
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
    }

    /**
     * Start a transaction. Returns the hosted payment URL + the reference.
     *
     * @return array{authorization_url:string, reference:string}
     *
     * @throws \RuntimeException on any API failure
     */
    public function initialize(string $email, float $amount, string $reference, string $callbackUrl): array
    {
        $res = Http::withToken((string) $this->secret())
            ->acceptJson()
            ->post($this->baseUrl().'/transaction/initialize', [
                'email' => $email,
                'amount' => (int) round($amount * 100),   // kobo
                'currency' => 'NGN',
                'reference' => $reference,
                'callback_url' => $callbackUrl,
            ]);

        $body = $res->json();
        if (! $res->successful() || ! ($body['status'] ?? false) || empty($body['data']['authorization_url'])) {
            throw new \RuntimeException($body['message'] ?? 'Could not start the Paystack payment.');
        }

        return [
            'authorization_url' => $body['data']['authorization_url'],
            'reference' => $body['data']['reference'] ?? $reference,
        ];
    }

    /**
     * Confirm a transaction server-side.
     *
     * @return array{success:bool, amount:float, reference:string, channel:?string}
     */
    public function verify(string $reference): array
    {
        $res = Http::withToken((string) $this->secret())
            ->acceptJson()
            ->get($this->baseUrl().'/transaction/verify/'.rawurlencode($reference));

        $body = $res->json();
        $data = $body['data'] ?? [];

        return [
            'success' => (bool) ($body['status'] ?? false) && ($data['status'] ?? null) === 'success',
            'amount' => isset($data['amount']) ? ((int) $data['amount']) / 100 : 0.0,
            'reference' => $data['reference'] ?? $reference,
            'channel' => $data['channel'] ?? null,
        ];
    }

    /** Verify a webhook body against the x-paystack-signature header (HMAC SHA512). */
    public function validSignature(string $payload, ?string $signature): bool
    {
        if (blank($signature)) {
            return false;
        }

        $expected = hash_hmac('sha512', $payload, (string) $this->secret());

        return hash_equals($expected, $signature);
    }
}
