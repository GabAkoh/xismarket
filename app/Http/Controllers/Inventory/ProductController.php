<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index(Request $request)
    {
        // Total stock per product as a join so we can both show and filter on it.
        $stockSub = DB::table('product_stocks')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->groupBy('product_id');

        $query = Product::query()->with('category')
            ->leftJoinSub($stockSub, 'ps', 'ps.product_id', '=', 'products.id')
            ->select('products.*', DB::raw('COALESCE(ps.qty, 0) as total_stock'));

        // "Needs attention" = no cost price AND no stock (incomplete catalogue records).
        $needsAttention = fn ($q) => $q->where('products.cost_price', 0)
            ->where(DB::raw('COALESCE(ps.qty, 0)'), '<=', 0);

        // "Not sellable" = no sale price OR no stock on hand (can't ring it up / nothing to sell).
        $notSellable = fn ($q) => $q->where(fn ($w) => $w
            ->where('products.sale_price', 0)
            ->orWhere(DB::raw('COALESCE(ps.qty, 0)'), '<=', 0));

        $attentionCount = $needsAttention(clone $query)->count();
        $sellableCount = $notSellable(clone $query)->count();

        match ($request->input('filter')) {
            'attention' => $needsAttention($query),
            'unsellable' => $notSellable($query),
            default => null,
        };

        $products = $query->orderBy('products.name')->paginate(20)->withQueryString();

        return view('inventory.products.index', compact('products', 'attentionCount', 'sellableCount'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();

        return view('inventory.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['track_stock'] = $request->boolean('track_stock');
        $data['is_active'] = $request->boolean('is_active');
        $this->applyImage($request, $data);

        Product::create($data);

        return redirect()->route('products.index')->with('status', 'Product added.');
    }

    public function edit(Product $product)
    {
        $this->authorizeTenant($product);
        $categories = Category::orderBy('name')->get();

        return view('inventory.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeTenant($product);
        $data = $this->validateData($request, $product);
        $data['track_stock'] = $request->boolean('track_stock');
        $data['is_active'] = $request->boolean('is_active');
        $this->applyImage($request, $data, $product);

        $product->update($data);

        return redirect()->route('products.index')->with('status', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeTenant($product);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return redirect()->route('products.index')->with('status', 'Product removed.');
    }

    protected function validateData(Request $request, ?Product $product = null): array
    {
        $tenantId = $this->tenancy->id();

        return $request->validate([
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('products', 'sku')->where('tenant_id', $tenantId)->ignore($product?->id),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'track_stock' => ['boolean'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:8192'],
        ]);
    }

    /**
     * Resolve the product image: store an uploaded file, or remove the
     * existing one, deleting the previous file from disk when replaced.
     */
    protected function applyImage(Request $request, array &$data, ?Product $product = null): void
    {
        unset($data['image']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');

            if ($product?->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
        } elseif ($request->boolean('remove_image') && $product?->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = null;
        }
    }

    protected function authorizeTenant(Product $product): void
    {
        abort_unless($product->tenant_id === $this->tenancy->id(), 404);
    }
}
