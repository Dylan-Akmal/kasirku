<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $movements = StockMovement::query()
            ->with(['product', 'user:id,name'])
            ->when($request->query('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($movements);
    }

    public function stockIn(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $movement = DB::transaction(function () use ($data, $product, $request) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $locked->stock += $data['quantity'];
            $locked->save();

            return StockMovement::create([
                'product_id' => $locked->id,
                'user_id' => $request->user()->id,
                'type' => 'in',
                'quantity' => $data['quantity'],
                'stock_after' => $locked->stock,
                'note' => $data['note'] ?? 'Stok masuk',
            ]);
        });

        return response()->json([
            'message' => 'Stok berhasil ditambahkan.',
            'data' => [
                'product' => $product->refresh(),
                'movement' => $movement,
            ],
        ], 201);
    }

    public function adjust(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'new_stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        $movement = DB::transaction(function () use ($data, $product, $request) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $difference = $data['new_stock'] - $locked->stock;

            if ($difference === 0) {
                return null;
            }

            $locked->stock = $data['new_stock'];
            $locked->save();

            return StockMovement::create([
                'product_id' => $locked->id,
                'user_id' => $request->user()->id,
                'type' => 'adjustment',
                'quantity' => $difference,
                'stock_after' => $locked->stock,
                'note' => $data['note'],
            ]);
        });

        if ($movement === null) {
            return response()->json([
                'message' => 'Stok baru sama dengan stok saat ini.',
            ], 422);
        }

        return response()->json([
            'message' => 'Stok berhasil disesuaikan.',
            'data' => [
                'product' => $product->refresh(),
                'movement' => $movement,
            ],
        ]);
    }
}