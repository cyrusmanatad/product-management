<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\VendorService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::select(['id', 'name', 'slug'])->orderBy('name')->get();
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $request->merge(['slug' => $request->input('slug') ?: Str::slug($request->input('name', ''))]);

        return $request->validate(['name' => 'required|string|max:100', 'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories')->where('vendor_id', app(TenantContext::class)->id())->ignore($category?->id)]]);
    }

    public function store(Request $request)
    {
        $category = Category::create($this->validated($request));
        VendorService::audit('category.created', ['category_id' => $category->id]);

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category));
        VendorService::audit('category.updated', ['category_id' => $category->id]);

        return response()->json(['data' => $category]);
    }

    public function destroy(Category $category)
    {
        abort_if($category->products()->exists(), 422, 'Move products before archiving their category.');
        $category->delete();
        VendorService::audit('category.archived', ['category_id' => $category->id]);

        return response()->json(['message' => 'Category archived.']);
    }
}
