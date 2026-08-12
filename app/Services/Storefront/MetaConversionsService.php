<?php

namespace App\Services\Storefront;

use App\Jobs\SendMetaEvent;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Meta (Facebook) Pixel + Conversions API integration.
 *
 * Two matching signals feed Meta for each conversion: the browser Pixel (fbq)
 * and this server-side Conversions API call. Both carry the same event_id so
 * Meta de-duplicates them — the Pixel supplies rich browser data (fbp/fbc
 * cookies, referrer) while the server call guarantees delivery past ad-blockers,
 * iOS tracking prompts and closed tabs (the same reason the Paystack webhook is
 * authoritative over the browser callback).
 *
 * Credentials live in the tenant's settings (meta.*), mirroring PaystackGateway:
 * the access token is encrypted at rest and never re-displayed. PII in user_data
 * (email/phone/name) is SHA-256 hashed before it ever leaves the app.
 */
class MetaConversionsService
{
    /** Is Meta tracking configured + enabled for the current tenant? */
    public function enabled(?Tenant $tenant = null): bool
    {
        $tenant ??= app(Tenancy::class)->current();

        return $tenant !== null
            && (bool) $tenant->setting('meta.enabled', false)
            && filled($this->pixelId($tenant))
            && filled($this->token($tenant));
    }

    /**
     * Should the browser Pixel render? Only needs the toggle + a Pixel ID — the
     * (server-only) access token isn't required to fire client-side fbq events.
     */
    public function browserEnabled(?Tenant $tenant = null): bool
    {
        $tenant ??= app(Tenancy::class)->current();

        return $tenant !== null
            && (bool) $tenant->setting('meta.enabled', false)
            && filled($this->pixelId($tenant));
    }

    public function pixelId(?Tenant $tenant = null): ?string
    {
        $tenant ??= app(Tenancy::class)->current();
        $fromSettings = $tenant?->setting('meta.pixel_id');

        return filled($fromSettings) ? (string) $fromSettings : config('services.meta.pixel_id');
    }

    /** Access token: tenant settings (encrypted) first, then env/config. */
    public function token(?Tenant $tenant = null): ?string
    {
        $tenant ??= app(Tenancy::class)->current();
        $enc = $tenant?->setting('meta.access_token');
        if (filled($enc)) {
            try {
                return Crypt::decryptString($enc);
            } catch (\Throwable $e) {
                // Corrupt/rotated app key — fall back to config.
            }
        }

        return config('services.meta.access_token');
    }

    public function testEventCode(?Tenant $tenant = null): ?string
    {
        $tenant ??= app(Tenancy::class)->current();
        $code = $tenant?->setting('meta.test_event_code');

        return filled($code) ? (string) $code : config('services.meta.test_event_code');
    }

    protected function graphVersion(): string
    {
        return (string) config('services.meta.graph_version', 'v21.0');
    }

    /**
     * SHA-256 hash a single identifier per Meta's rules (trim + lowercase).
     * Returns null for blank input so it's dropped from user_data.
     */
    public function hash(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : hash('sha256', mb_strtolower($value));
    }

    /** Normalise a phone to digits (with country code) before hashing. */
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits === '' ? null : $digits;
    }

    /**
     * Build the hashed user_data block from a request + known identifiers.
     * client_ip_address / client_user_agent / fbp / fbc are sent unhashed.
     *
     * @param  array{email?:?string,phone?:?string,first_name?:?string,last_name?:?string}  $identity
     * @return array<string,string>
     */
    public function userData(?Request $request, array $identity = []): array
    {
        return array_filter([
            'em' => $this->hash($identity['email'] ?? null),
            'ph' => $this->hash($this->normalizePhone($identity['phone'] ?? null)),
            'fn' => $this->hash($identity['first_name'] ?? null),
            'ln' => $this->hash($identity['last_name'] ?? null),
            'client_ip_address' => $request?->ip(),
            'client_user_agent' => $request?->userAgent(),
            'fbp' => $request?->cookie('_fbp'),
            'fbc' => $this->fbc($request),
        ], fn ($v) => filled($v));
    }

    /**
     * Meta's click id (fbc). Prefer the _fbc cookie; otherwise synthesise it
     * from an ?fbclid= landing param in the form fb.1.<ts>.<fbclid>.
     */
    protected function fbc(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }
        if ($cookie = $request->cookie('_fbc')) {
            return $cookie;
        }
        $fbclid = $request->query('fbclid');

        return filled($fbclid) ? 'fb.1.'.$request->server('REQUEST_TIME', time()).'.'.$fbclid : null;
    }

    /**
     * Queue a Conversions API event for the current tenant, hashing PII and
     * capturing browser signals (fbp/fbc cookies, IP, UA) from the request.
     * No-op when Meta isn't configured. Pass event_id matching the browser
     * Pixel's eventID so Meta de-duplicates the browser + server pair.
     *
     * @param  array{event_id?:?string,identity?:array,custom_data?:?array,event_source_url?:?string,action_source?:string}  $opts
     */
    public function track(?Request $request, string $eventName, array $opts = []): void
    {
        $tenant = app(Tenancy::class)->current();
        if (! $tenant || ! $this->enabled($tenant)) {
            return;
        }

        SendMetaEvent::dispatch($tenant->id, $eventName, [
            'event_id' => $opts['event_id'] ?? null,
            'action_source' => $opts['action_source'] ?? 'website',
            'event_source_url' => $opts['event_source_url'] ?? $request?->fullUrl(),
            'user_data' => $this->userData($request, $opts['identity'] ?? []),
            'custom_data' => $opts['custom_data'] ?? null,
        ]);
    }

    /**
     * POST one event to the Conversions API for a specific tenant. No-op when
     * Meta isn't configured. Throws on an API failure so the caller/queue can
     * retry + report.
     *
     * @param  array{user_data?:array,custom_data?:array,event_id?:string,event_source_url?:?string,action_source?:string}  $event
     */
    public function postEvent(Tenant $tenant, string $eventName, array $event): void
    {
        if (! $this->enabled($tenant)) {
            return;
        }

        $payload = array_filter([
            'event_name' => $eventName,
            'event_time' => time(),
            'event_id' => $event['event_id'] ?? null,
            'event_source_url' => $event['event_source_url'] ?? null,
            'action_source' => $event['action_source'] ?? 'website',
            'user_data' => $event['user_data'] ?? [],
            'custom_data' => $event['custom_data'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        $body = array_filter([
            'data' => [$payload],
            'access_token' => $this->token($tenant),
            'test_event_code' => $this->testEventCode($tenant),
        ], fn ($v) => filled($v));

        $url = 'https://graph.facebook.com/'.$this->graphVersion().'/'.$this->pixelId($tenant).'/events';

        $res = Http::acceptJson()->timeout(10)->post($url, $body);

        if (! $res->successful()) {
            $err = $res->json('error.message') ?? $res->body();
            throw new \RuntimeException('Meta Conversions API rejected the '.$eventName.' event: '.$err);
        }
    }
}
