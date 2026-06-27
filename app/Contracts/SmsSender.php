<?php

namespace App\Contracts;

/**
 * Outbound SMS abstraction. Bound in AppServiceProvider to a concrete driver
 * chosen by config('services.sms.provider'). Until a real provider (Termii,
 * Twilio, …) is configured, the bound implementation is a no-op that only logs.
 */
interface SmsSender
{
    /**
     * Send a single SMS. Best-effort: implementations may throw on a hard
     * failure — callers guard the call so a failure never breaks the request.
     */
    public function send(string $to, string $message): void;
}
