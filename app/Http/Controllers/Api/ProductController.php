<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $products = Product::query()
            ->with(['productCategory', 'brand'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('productCategory', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['productCategory', 'brand', 'branch', 'creator', 'projects.builder']);

        return response()->json($product);
    }
}
