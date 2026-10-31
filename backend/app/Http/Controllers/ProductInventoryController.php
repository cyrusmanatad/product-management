<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Http\Resources\ProductResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductInventoryController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    /**
     * Overall inventory summary counts
     * GET /inventory/total
     */
    public function total()
    {
        $summary = Product::query()
            ->withSum('inventories as total_stock', 'stock_quantity')
            ->withSum('inventories as total_reserved', 'reserved_quantity')
            ->get();

        return response()->json([
            'data' => [
                'total_products' => Product::count(),
                'total_units_sold' => (int) OrderItem::whereHas('order', fn ($query) => $query->withTrashed()->where('payment_status', 'paid')->where('status', '!=', 'cancelled'))->sum('quantity'),
                'total_published' => Product::where('status', ProductStatus::PUBLISHED)->count(),
                'total_draft' => Product::where('status', ProductStatus::DRAFT)->count(),
                'total_inactive' => Product::where('status', ProductStatus::INACTIVE)->count(),
                'total_out_stock' => Product::where('status', ProductStatus::OUT_STOCK)->count(),
                'total_stock' => (int) $summary->sum('total_stock'),
                'total_reserved' => (int) $summary->sum('total_reserved'),
                'total_available' => (int) ($summary->sum('total_stock') - $summary->sum('total_reserved')),
            ],
        ]);
    }

    /**
     * Sales — Published products
     * GET /inventory/sales
     */
    public function sales(Request $request)
    {
        $query = $this->productService
            ->baseQuery()
            ->where('status', ProductStatus::PUBLISHED);

        if ($request->filled('search')) {
            $this->productService->applySearch($query, $request->search);
        }

        return ProductResource::collection($query->paginate($request->per_page ?? 10));
    }

    /**
     * Stocks — All products with stock summary
     * GET /inventory/stocks
     */
    public function stocks(Request $request)
    {
        $query = Product::query()
            ->select(['id', 'user_id', 'category_id', 'title', 'slug', 'status', 'created_at'])
            ->with([
                'user:id,name,email',
                'category:id,name',
                'variants:id,product_id,sku,uom,price,sale_price,currency,is_active',
                'variants.inventory:id,variant_id,stock_quantity,reserved_quantity',
            ])
            ->withSum('inventories as total_stock', 'stock_quantity')
            ->withSum('inventories as total_reserved', 'reserved_quantity')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%")
                    ->orWhereHas('variants', fn ($q2) => $q2->where('sku', 'like', "%{$search}%")
                        ->orWhere('desc', 'like', "%{$search}%")
                    );
            });
        }

        // Filter by stock level
        if ($request->filled('stock_filter')) {
            match ($request->stock_filter) {
                'low' => $query->havingRaw('total_stock <= 10 AND total_stock > 0'),
                'in_stock' => $query->havingRaw('total_stock > 0'),
                'empty' => $query->havingRaw('total_stock = 0'),
                default => null
            };
        }

        return ProductResource::collection($query->paginate($request->per_page ?? 10));
    }

    /**
     * Unavailable — Inactive, Out of Stock, Draft products
     * GET /inventory/unavailable
     */
    public function unavailable(Request $request)
    {
        $query = $this->productService
            ->baseQuery()
            ->whereIn('status', [
                ProductStatus::INACTIVE,
                ProductStatus::OUT_STOCK,
                ProductStatus::DRAFT,
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $this->productService->applySearch($query, $request->search);
        }

        return ProductResource::collection($query->paginate($request->per_page ?? 10));
    }
}
