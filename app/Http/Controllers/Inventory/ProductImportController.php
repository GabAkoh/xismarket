<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\ShopifyProductImporter;
use Illuminate\Http\Request;

class ProductImportController extends Controller
{
    public function form()
    {
        return view('inventory.products.import');
    }

    public function import(Request $request, ShopifyProductImporter $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        $result = $importer->import(
            $request->file('file')->getRealPath(),
            downloadImages: $request->boolean('download_images'),
        );

        if ($result['created'] === 0 && $result['updated'] === 0 && $result['errors']) {
            return back()->with('error', $result['errors'][0]);
        }

        $msg = "Import complete — {$result['created']} created, {$result['updated']} updated"
            .($result['images'] ? ", {$result['images']} images" : '')
            .($result['skipped'] ? ", {$result['skipped']} skipped" : '').'.';

        return back()->with('status', $msg)->with('importErrors', $result['errors']);
    }
}
