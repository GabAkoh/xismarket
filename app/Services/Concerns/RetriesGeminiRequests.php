<?php

namespace App\Services\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * Small retry helper for the Gemini HTTP calls (image generation + embeddings).
 *
 * Retries only *transient* failures — rate limits (429), gateway/5xx errors and
 * connection drops — with capped exponential backoff. Client errors (400/401/403)
 * are config/credential problems that won't fix themselves, so they surface
 * immediately. Pass attempts=1 on latency-critical paths (live search queries)
 * to keep the existing fast-fail-to-fuzzy behaviour.
 */
trait RetriesGeminiRequests
{
    /** Transient HTTP statuses worth another try. */
    protected function isRetriableStatus(int $status): bool
    {
        return in_array($status, [408, 425, 429, 500, 502, 503, 504], true);
    }

    /**
     * Invoke $send() up to $attempts times, retrying transient HTTP errors and
     * connection failures. Returns the last Response; re-throws the final
     * ConnectionException if every attempt fails to connect.
     *
     * @param  callable():Response  $send
     */
    protected function sendWithRetry(callable $send, int $attempts): Response
    {
        $attempts = max(1, $attempts);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $send();
                if (! $response->failed()
                    || $attempt >= $attempts
                    || ! $this->isRetriableStatus($response->status())) {
                    return $response;
                }
            } catch (ConnectionException $e) {
                $lastException = $e;
                if ($attempt >= $attempts) {
                    throw $e;
                }
            }

            // Capped exponential backoff: 0.4s, 0.8s, 1.6s … max 3s.
            usleep((int) (min(3.0, 0.4 * (2 ** ($attempt - 1))) * 1_000_000));
        }

        // Only reached if the final attempt threw; rethrow it for the caller.
        throw $lastException ?? new \RuntimeException('Gemini request failed after retries.');
    }
}
