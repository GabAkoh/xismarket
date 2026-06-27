<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Jobs\ImportOdooProductsJob;
use App\Jobs\ImportShopifyProductsJob;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductImportController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function form()
    {
        $lastImport = Cache::get(ImportShopifyProductsJob::resultKey($this->tenancy->id()));

        return view('inventory.products.import', compact('lastImport'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20 MB
            'source' => ['nullable', 'in:shopify,odoo'],
        ]);

        // Persist the upload (the temp file is gone after the request) and import
        // it in the background so large files / image downloads don't block here.
        $path = $request->file('file')->store('imports', 'local');

        if (! $path) {
            return back()->with('error', 'Could not save the uploaded file — please try the import again.');
        }

        // Forget the previous result so the page reflects this run once it finishes.
        Cache::forget(ImportShopifyProductsJob::resultKey($this->tenancy->id()));

        if ($request->input('source') === 'odoo') {
            // Odoo: create only products whose name isn't already here.
            ImportOdooProductsJob::dispatch($this->tenancy->id(), $path);
        } else {
            ImportShopifyProductsJob::dispatch(
                $this->tenancy->id(),
                $path,
                $request->boolean('download_images'),
                $request->boolean('refresh_images'),
            );
        }

        return back()
            ->with('status', 'Import queued — the summary will appear here once it finishes (usually a few seconds).')
            ->with('justQueued', true);
    }
}
