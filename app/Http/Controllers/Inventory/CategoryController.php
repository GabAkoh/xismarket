<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        $categories = Category::with('parent')
            ->withCount('products')
            ->orderBy('name')
            ->paginate(20);

        return view('inventory.categories.index', compact('categories'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();

        return view('inventory.categories.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        Category::create($data);

        return redirect()->route('categories.index')->with('status', 'Category added.');
    }

    public function edit(Category $category)
    {
        $this->authorizeTenant($category);
        $categories = Category::where('id', '!=', $category->id)->orderBy('name')->get();

        return view('inventory.categories.edit', compact('category', 'categories'));
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeTenant($category);
        $data = $this->validateData($request, $category);
        $data['slug'] = $this->uniqueSlug($data['name'], $category->id);

        $category->update($data);

        return redirect()->route('categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $this->authorizeTenant($category);
        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Category removed.');
    }

    protected function validateData(Request $request, ?Category $category = null): array
    {
        $tenantId = $this->tenancy->id();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
                $category ? Rule::notIn([$category->id]) : 'nullable',
            ],
        ]);
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Category::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    protected function authorizeTenant(Category $category): void
    {
        abort_unless($category->tenant_id === $this->tenancy->id(), 404);
    }
}
