<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Services\Images\ImageGenerator;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * AI image operations for a product: background removal/replacement, colour
 * variants, side/angle views and human-model lifestyle shots. Each runs the
 * product's chosen source image through the ImageGenerator and stores the
 * result as a new gallery image.
 */
class ProductImageAiController extends Controller
{
    public function __construct(protected Tenancy $tenancy, protected ImageGenerator $generator) {}

    public function generate(Request $request, Product $product)
    {
        $this->authorizeTenant($product);

        $data = $request->validate([
            'operation' => ['required', Rule::in(array_keys(ImageGenerator::OPERATIONS))],
            'detail' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'integer'], // a gallery image id; defaults to the cover
        ]);

        if (! $this->generator->configured()) {
            return response()->json([
                'message' => 'AI image generation is not configured. Add an image-API key (IMAGE_AI_KEY) to enable it.',
            ], 422);
        }

        // Resolve the source image bytes — a chosen gallery image, else the cover.
        [$bytes, $mime] = $this->sourceImage($product, $request->integer('source'));
        if ($bytes === null) {
            return response()->json(['message' => 'This product has no source image to work from. Add a cover image first.'], 422);
        }

        try {
            $instruction = $this->generator->instructionFor($data['operation'], $data['detail'] ?? '');
            $result = $this->generator->edit($bytes, $mime, $instruction);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Store the generated image and append it to the gallery.
        $ext = str_contains($result['mime'], 'png') ? 'png' : (str_contains($result['mime'], 'webp') ? 'webp' : 'jpg');
        $path = 'products/ai-'.\Illuminate\Support\Str::random(32).'.'.$ext;
        Storage::disk('public')->put($path, $result['data']);

        $image = $product->images()->create([
            'path' => $path,
            'position' => (int) $product->images()->max('position') + 1,
            'source' => 'ai-'.$data['operation'],
        ]);

        return response()->json([
            'message' => 'Image generated.',
            'image' => ['id' => $image->id, 'url' => $image->url(), 'source' => $image->source],
        ]);
    }

    /**
     * Raw bytes + mime for the chosen source image (a gallery image id, or the
     * product cover when none is given). Returns [null, null] if nothing exists.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function sourceImage(Product $product, ?int $sourceId): array
    {
        $path = null;
        if ($sourceId) {
            $path = $product->images()->whereKey($sourceId)->value('path');
        }
        $path = $path ?: $product->image_path ?: $product->images()->orderBy('position')->value('path');
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return [null, null];
        }

        return [Storage::disk('public')->get($path), Storage::disk('public')->mimeType($path) ?: 'image/jpeg'];
    }

    protected function authorizeTenant(Product $product): void
    {
        abort_unless((int) $product->tenant_id === (int) $this->tenancy->id(), 404);
    }
}
