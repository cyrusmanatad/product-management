<?php

namespace App\Http\Controllers;

use App\DTOs\ProductData;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->select(['id', 'user_id', 'category_id', 'base_sku', 'title', 'description', 'slug', 'status', 'created_at'])
            ->with([
                'images',
                'user:id,name,email',
                'variants:id,product_id,sku,uom,price,sale_price,currency,attributes',
                'variants.inventory:id,variant_id,stock_quantity,reserved_quantity',
                'category:id,name',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $categories = $request->category;
            $query->whereHas('category', function ($q) use ($categories) {
                $q->whereIn('id', $categories);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($q2) use ($search) {
                        $q2->where('sku', 'like', "%{$search}%")
                            ->orWhere('uom', 'like', "%{$search}%");
                    });
            });
        }

        return ProductResource::collection($query->paginate(10));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $dto = ProductData::fromRequest(
            $request->validated(),
            Auth::id()
        );

        try {
            $product = $this->productService->create($dto);

            return response()->json([
                'message' => 'Product created successfully',
                'data' => $product,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $th) {
            Log::error($th);

            return response()->json([
                'message' => 'Failed to create product',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json($product);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);
        try {

            $dto = ProductData::fromRequest(
                $request->validated(),
                Auth::id()
            );

            $product = $this->productService->update($product, $dto);

            return response()->json([
                'message' => 'Product updated successfully',
                'data' => $product,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $th) {
            Log::error($th);

            return response()->json([
                'message' => 'Failed to update product',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $this->productService->delete($product);

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}
