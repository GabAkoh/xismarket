<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Storefront\MetaConversionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one Meta Conversions API event off-request so checkout/settlement never
 * waits on Meta. The event array already contains SHA-256-hashed user_data
 * (built in the web request that had the cookies/PII) — no raw PII is stored in
 * the queue. Retried a few times, then reported; a failed Meta call must never
 * break the order flow.
 *
 * @see MetaConversionsService::postEvent()
 */
class SendMetaEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 20;

    /**
     * @param  array{user_data?:array,custom_data?:array,event_id?:string,event_source_url?:?string}  $event
     */
    public function __construct(
        public int $tenantId,
        public string $eventName,
        public array $event,
    ) {}

    public function handle(MetaConversionsService $meta): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }

        $meta->postEvent($tenant, $this->eventName, $this->event);
    }

    public function failed(\Throwable $e): void
    {
        report($e);
    }
}
