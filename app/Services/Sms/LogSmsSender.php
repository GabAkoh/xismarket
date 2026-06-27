<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder SMS sender used until a real provider is configured. It does NOT
 * send anything — it records the message to the application log so the alert
 * pipeline is exercised end-to-end without a gateway.
 *
 * To go live: add a driver (e.g. TermiiSmsSender, TwilioSmsSender), set
 * SMS_PROVIDER in .env, and map it in AppServiceProvider's binding.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('[SMS not sent — no provider configured] to '.$to.': '.$message);
    }
}
