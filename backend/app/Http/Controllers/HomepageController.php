<?php

namespace App\Http\Controllers;

use App\Http\Resources\HomepageProductResource;
use App\Models\Vendor;
use App\Services\HomepageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomepageController extends Controller
{
    public function stores(Request $request)
    {
        $data = $request->validate(['search' => 'nullable|string|max:100']);
        $query = Vendor::where('is_active', true)->select(['id', 'name', 'slug', 'currency'])
            ->when($data['search'] ?? null, fn ($query, $search) => $query->whereLike('name', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id');

        return JsonResource::collection($query->paginate(12));
    }

    public function featured(HomepageService $service)
    {
        return HomepageProductResource::collection($service->featured()->paginate(12));
    }

    public function selections(HomepageService $service)
    {
        return HomepageProductResource::collection($service->featured(false)->paginate(12));
    }

    public function candidates(Request $request, Vendor $vendor, HomepageService $service)
    {
        $data = $request->validate(['search' => 'nullable|string|max:100']);

        return HomepageProductResource::collection($service->candidates($vendor, $data['search'] ?? '')->paginate(12));
    }

    public function store(Request $request, Vendor $vendor, HomepageService $service)
    {
        $data = $request->validate(['product_id' => 'required|integer|min:1']);
        $service->feature($vendor, $data['product_id'], $request->user()->id);

        return response()->json(['message' => 'Product featured on the homepage.']);
    }

    public function destroy(Vendor $vendor, int $productId, HomepageService $service)
    {
        $service->remove($vendor, $productId);

        return response()->json(['message' => 'Product removed from the homepage.']);
    }
}
