<?php

namespace App\Services\Images;

use App\Services\Concerns\RetriesGeminiRequests;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Provider-agnostic product-image generation/editing.
 *
 * Powers the AI image operations (background removal, colour variants, side
 * views, model shots) — each is just a different natural-language instruction
 * applied to a source image. The default provider is Google's Gemini image
 * model ("Nano Banana"); a 'stub' provider echoes the source image so the rest
 * of the pipeline can be exercised without an external call or key.
 */
class ImageGenerator
{
    use RetriesGeminiRequests;

    /** Human-readable instruction per operation. {extra} is filled from caller params. */
    public const OPERATIONS = [
        'background' => 'Remove the existing background and place the product on a clean, plain {extra} studio background. Keep the product itself unchanged, sharply lit and centered.',
        'color' => 'Recolour the main product to {extra}. Keep the shape, material, lighting and background exactly the same — only change the colour of the product.',
        'angle' => 'Generate a realistic {extra} view of this same product, consistent in design, colour and proportions with the original.',
        'model' => 'Show this product being used or worn by a friendly human model in a bright, natural lifestyle setting. Keep the product faithful to the original; the person should look natural and the product should be the focus.',
    ];

    public function configured(): bool
    {
        return $this->provider() === 'stub' || $this->apiKey() !== '';
    }

    /**
     * The Gemini API key to use: the current tenant's own key (set from the AI
     * Tools sidebar preference) takes precedence over the server-wide env
     * fallback, so each store can bring its own key.
     */
    public function apiKey(): string
    {
        $tenantKey = app(Tenancy::class)->current()?->setting('ai.gemini_key');

        return trim((string) ($tenantKey ?: config('services.image_ai.key')));
    }

    public function provider(): string
    {
        return (string) config('services.image_ai.provider', 'gemini');
    }

    /**
     * Build the instruction for an operation + caller-supplied detail.
     */
    public function instructionFor(string $operation, string $extra = ''): string
    {
        $template = self::OPERATIONS[$operation] ?? null;
        if (! $template) {
            throw new RuntimeException("Unknown image operation: {$operation}");
        }

        $defaults = ['background' => 'white', 'color' => 'a different colour', 'angle' => 'side'];
        $extra = trim($extra) !== '' ? trim($extra) : ($defaults[$operation] ?? '');

        return str_replace('{extra}', $extra, $template);
    }

    /**
     * Edit a source image with a natural-language instruction.
     *
     * @param  string  $bytes  raw source image bytes
     * @param  string  $mime   source mime type (e.g. image/png)
     * @return array{data: string, mime: string}  generated image bytes + mime
     */
    public function edit(string $bytes, string $mime, string $instruction): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('AI image generation is not configured. Set IMAGE_AI_KEY in your environment.');
        }

        return match ($this->provider()) {
            'stub' => ['data' => $bytes, 'mime' => $mime],
            'gemini' => $this->editWithGemini($bytes, $mime, $instruction),
            default => throw new RuntimeException('Unsupported image AI provider: '.$this->provider()),
        };
    }

    /**
     * Call Google's Gemini image model. Sends the source image inline with the
     * instruction and returns the first inline image part from the response.
     */
    protected function editWithGemini(string $bytes, string $mime, string $instruction): array
    {
        $key = $this->apiKey();
        $model = config('services.image_ai.model');
        $endpoint = rtrim((string) config('services.image_ai.endpoint'), '/');
        $url = "{$endpoint}/models/{$model}:generateContent";

        // Retry transient rate-limit / 5xx / connection failures with backoff;
        // hard errors (bad key, depleted quota 4xx) surface on the first try.
        $response = $this->sendWithRetry(fn () => Http::timeout(120)
            ->withHeaders(['x-goog-api-key' => $key])
            ->post($url, [
                'contents' => [[
                    'parts' => [
                        ['text' => $instruction],
                        ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
                    ],
                ]],
            ]), attempts: 3);

        if ($response->failed()) {
            $msg = $response->json('error.message') ?? $response->body();
            throw new RuntimeException('Image AI request failed: '.\Illuminate\Support\Str::limit((string) $msg, 300));
        }

        foreach ($response->json('candidates.0.content.parts', []) as $part) {
            $inline = $part['inline_data'] ?? $part['inlineData'] ?? null;
            if ($inline && ! empty($inline['data'])) {
                return [
                    'data' => base64_decode($inline['data']),
                    'mime' => $inline['mime_type'] ?? $inline['mimeType'] ?? 'image/png',
                ];
            }
        }

        throw new RuntimeException('The image AI returned no image. Try a clearer source image or a different instruction.');
    }
}
