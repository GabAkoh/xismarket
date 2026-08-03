<?php

namespace App\Services\Search;

use App\Services\Concerns\RetriesGeminiRequests;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Provider-agnostic text-embedding client that powers semantic product search.
 *
 * The default provider is Google's Gemini embedding model, reusing the same
 * account/key as the AI image tools. A 'stub' provider produces deterministic,
 * meaning-free-but-token-aware vectors (feature-hashed bag of words) so the rest
 * of the search pipeline can be exercised offline and in tests without a key or
 * an external call.
 *
 * Vectors are returned as plain float arrays of length config('services.embeddings.dims').
 */
class EmbeddingClient
{
    use RetriesGeminiRequests;

    public function provider(): string
    {
        return (string) config('services.embeddings.provider', 'gemini');
    }

    public function dims(): int
    {
        return max(1, (int) config('services.embeddings.dims', 768));
    }

    /** Whether a real (or stub) provider is usable — false means "no semantic search". */
    public function configured(): bool
    {
        return $this->provider() === 'stub' || $this->apiKey() !== '';
    }

    /**
     * The Gemini API key to use, reusing the AI image tools' key: the current
     * tenant's own key (set from the AI Tools sidebar preference) takes
     * precedence over the server-wide env fallback.
     */
    public function apiKey(): string
    {
        $tenantKey = app(Tenancy::class)->current()?->setting('ai.gemini_key');

        return trim((string) ($tenantKey ?: config('services.embeddings.key')));
    }

    /**
     * Embed a single string. $timeout (seconds) overrides the default request
     * timeout — pass a short one for interactive/search queries so a slow or
     * unreachable provider fast-fails instead of hanging the caller.
     *
     * @return array<int, float>
     */
    public function embed(string $text, ?float $timeout = null): array
    {
        return $this->embedMany([$text], $timeout)[0];
    }

    /**
     * Embed many strings in one round-trip where the provider supports it.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>> one vector per input, in order
     */
    public function embedMany(array $texts, ?float $timeout = null): array
    {
        if ($texts === []) {
            return [];
        }

        if (! $this->configured()) {
            throw new RuntimeException('Semantic search is not configured. Set EMBEDDINGS_KEY in your environment.');
        }

        return match ($this->provider()) {
            'stub' => array_map(fn ($t) => $this->stubVector((string) $t), array_values($texts)),
            'gemini' => $this->embedWithGemini(array_values($texts), $timeout),
            default => throw new RuntimeException('Unsupported embeddings provider: '.$this->provider()),
        };
    }

    /**
     * Embed each text via Gemini's embedContent endpoint. embedContent (singular)
     * is supported by every current embedding model (gemini-embedding-001, the
     * text-embedding series); the synchronous batch endpoint is not, so we call
     * once per text. outputDimensionality pins the vector length to the configured
     * dims so stored product vectors and query vectors always match.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    protected function embedWithGemini(array $texts, ?float $timeout = null): array
    {
        $key = $this->apiKey();
        $model = (string) config('services.embeddings.model');
        $modelPath = str_starts_with($model, 'models/') ? $model : "models/{$model}";
        $endpoint = rtrim((string) config('services.embeddings.endpoint'), '/');
        $url = "{$endpoint}/{$modelPath}:embedContent";

        $timeout = $timeout ?? (float) config('services.embeddings.timeout', 60);

        // Retry transient failures on the background path (backfill/observer),
        // but single-shot on the short-timeout live-search path so a flaky
        // provider still fast-fails to fuzzy results instead of stacking retries.
        $attempts = $timeout <= 5.0 ? 1 : 3;

        return array_map(function ($text) use ($url, $key, $modelPath, $timeout, $attempts) {
            $response = $this->sendWithRetry(fn () => Http::timeout($timeout)
                ->connectTimeout(min($timeout, 5.0))
                ->withHeaders(['x-goog-api-key' => $key])
                ->post($url, [
                    'model' => $modelPath,
                    'content' => ['parts' => [['text' => $text]]],
                    'outputDimensionality' => $this->dims(),
                ]), $attempts);

            if ($response->failed()) {
                $msg = $response->json('error.message') ?? $response->body();
                throw new RuntimeException('Embedding request failed: '.Str::limit((string) $msg, 300));
            }

            $values = $response->json('embedding.values', $response->json('embedding.value', []));

            return array_map('floatval', $values);
        }, $texts);
    }

    /**
     * Deterministic offline vector: feature-hash the text's tokens into the
     * vector space and L2-normalise. Texts sharing tokens land close together,
     * which is enough to exercise the semantic ranking path without a network
     * call. NOT a substitute for a real model's meaning-awareness.
     *
     * @return array<int, float>
     */
    protected function stubVector(string $text): array
    {
        $dims = $this->dims();
        $vec = array_fill(0, $dims, 0.0);

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($tokens as $token) {
            // Two independent hashes (index + sign) to soften collisions.
            $idx = crc32($token) % $dims;
            $sign = (crc32('s:'.$token) % 2) === 0 ? 1.0 : -1.0;
            $vec[$idx] += $sign;
        }

        $norm = 0.0;
        foreach ($vec as $v) {
            $norm += $v * $v;
        }
        $norm = sqrt($norm);
        if ($norm > 0) {
            foreach ($vec as $i => $v) {
                $vec[$i] = $v / $norm;
            }
        }

        return $vec;
    }
}
