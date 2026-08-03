<?php

namespace App\Http\Controllers;

use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Per-tenant Gemini API key for the AI tools (product-image generation and
 * semantic-search embeddings). Stored under the tenant's settings (ai.gemini_key)
 * and preferred over the IMAGE_AI_KEY/EMBEDDINGS_KEY env fallback, so each store
 * can bring its own key from a sidebar preference instead of a server .env edit.
 */
class AiSettingsController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        return view('settings.ai', ['store' => $this->tenancy->current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'gemini_key' => ['nullable', 'string', 'max:200'],
            'remove_key' => ['nullable'],
        ]);

        $store = $this->tenancy->current();
        $settings = $store->settings ?? [];
        $ai = $settings['ai'] ?? [];

        if ($request->boolean('remove_key')) {
            $ai['gemini_key'] = null;
        } elseif (($key = trim((string) ($data['gemini_key'] ?? ''))) !== '') {
            // Leaving the field blank keeps the existing key untouched, so the
            // saved secret is never echoed back to the browser to be re-posted.
            $ai['gemini_key'] = $key;
        }

        $settings['ai'] = $ai;
        $store->update(['settings' => $settings]);

        return redirect()->route('ai.settings')->with('status', 'AI settings updated.');
    }
}
