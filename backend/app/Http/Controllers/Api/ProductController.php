<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $products = Product::query()
            ->with('category:id,name')
            ->when($request->query('q'), function ($query, $q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->when($request->query('sku'), fn ($query, $sku) => $query->where('sku', $sku))
            ->when($request->query('category_id'), fn ($query, $id) => $query->where('category_id', $id))
            ->when($request->boolean('low_stock'), fn ($query) => $query->lowStock())
            ->orderBy('name')
            ->paginate($perPage);

        if (! $request->user()->isAdmin()) {
            $products->getCollection()->each->makeHidden('price_buy');
        }

        return response()->json($products);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $product->load('category:id,name');

        if (! $request->user()->isAdmin()) {
            $product->makeHidden('price_buy');
        }

        return response()->json(['data' => $product]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:products,sku'],
            'price_buy' => ['required', 'integer', 'min:0'],
            'price_sell' => ['required', 'integer', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'min_stock' => ['sometimes', 'integer', 'min:0'],
        ]);

        $data['stock'] = $data['stock'] ?? 0;
        $data['min_stock'] = $data['min_stock'] ?? 5;

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);

            if ($product->stock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'type' => 'in',
                    'quantity' => $product->stock,
                    'stock_after' => $product->stock,
                    'note' => 'Stok awal',
                ]);
            }

            return $product;
        });

        return response()->json([
            'message' => 'Produk berhasil dibuat.',
            'data' => $product->load('category:id,name'),
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:products,sku,'.$product->id],
            'price_buy' => ['sometimes', 'required', 'integer', 'min:0'],
            'price_sell' => ['sometimes', 'required', 'integer', 'min:0'],
            'min_stock' => ['sometimes', 'integer', 'min:0'],
        ]);

        $product->update($data);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data' => $product->load('category:id,name'),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $path = $request->file('image')->store('products', 'public');
        $product->update(['image_path' => $path]);

        return response()->json([
            'message' => 'Foto produk berhasil diunggah.',
            'data' => $product->load('category:id,name'),
        ]);
    }

    public function deleteImage(Product $product): JsonResponse
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $product->update(['image_path' => null]);
        }

        return response()->json([
            'message' => 'Foto produk dihapus.',
        ]);
    }
}