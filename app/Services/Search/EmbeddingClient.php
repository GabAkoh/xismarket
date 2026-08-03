<?php

namespace App\Services\Search;

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
        return $this->provider() === 'stub' || ! empty(config('services.embeddings.key'));
    }

    /**
     * Embed a single string.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        return $this->embedMany([$text])[0];
    }

    /**
     * Embed many strings in one round-trip where the provider supports it.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>> one vector per input, in order
     */
    public function embedMany(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        if (! $this->configured()) {
            throw new RuntimeException('Semantic search is not configured. Set EMBEDDINGS_KEY in your environment.');
        }

        return match ($this->provider()) {
            'stub' => array_map(fn ($t) => $this->stubVector((string) $t), array_values($texts)),
            'gemini' => $this->embedWithGemini(array_values($texts)),
            default => throw new RuntimeException('Unsupported embeddings provider: '.$this->provider()),
        };
    }

    /**
     * Call Gemini's batchEmbedContents endpoint and return the vectors in order.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    protected function embedWithGemini(array $texts): array
    {
        $key = config('services.embeddings.key');
        $model = (string) config('services.embeddings.model');
        $modelPath = str_starts_with($model, 'models/') ? $model : "models/{$model}";
        $endpoint = rtrim((string) config('services.embeddings.endpoint'), '/');
        $url = "{$endpoint}/{$modelPath}:batchEmbedContents";

        $response = Http::timeout(60)
            ->withHeaders(['x-goog-api-key' => $key])
            ->post($url, [
                'requests' => array_map(fn ($text) => [
                    'model' => $modelPath,
                    'content' => ['parts' => [['text' => $text]]],
                ], $texts),
            ]);

        if ($response->failed()) {
            $msg = $response->json('error.message') ?? $response->body();
            throw new RuntimeException('Embedding request failed: '.Str::limit((string) $msg, 300));
        }

        $embeddings = $response->json('embeddings', []);
        if (count($embeddings) !== count($texts)) {
            throw new RuntimeException('Embedding provider returned '.count($embeddings).' vectors for '.count($texts).' inputs.');
        }

        return array_map(function ($e) {
            $values = $e['values'] ?? $e['value'] ?? [];

            return array_map('floatval', $values);
        }, $embeddings);
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
