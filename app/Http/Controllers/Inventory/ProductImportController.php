<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Jobs\ImportShopifyProductsJob;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class ProductImportController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function form()
    {
        return view('inventory.products.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        // Persist the upload (the temp file is gone after the request) and import
        // it in the background so large files / image downloads don't block here.
        $path = $request->file('file')->store('imports', 'local');

        if (! $path) {
            return back()->with('error', 'Could not save the uploaded file — please try the import again.');
        }

        ImportShopifyProductsJob::dispatch(
            $this->tenancy->id(),
            $path,
            $request->boolean('download_images'),
        );

        return back()->with('status', 'Import queued — your products will appear shortly as it processes in the background.');
    }
}
