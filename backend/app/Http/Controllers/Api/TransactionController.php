<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StoreSetting;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $transactions = Transaction::query()
            ->with('user:id,name')
            ->withCount('items')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when(
                $user->isAdmin() && $request->query('user_id'),
                fn ($q) => $q->where('user_id', $request->query('user_id'))
            )
            ->when($request->query('q'), fn ($q, $term) => $q->where('invoice_no', 'like', "%{$term}%"))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('payment_method'), fn ($q, $method) => $q->where('payment_method', $method))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($transactions);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $this->ensureCanView($request, $transaction);

        return response()->json([
            'data' => $this->present($request, $transaction),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'discount_type' => ['sometimes', Rule::in(['none', 'nominal', 'percent'])],
            'discount_value' => ['sometimes', 'integer', 'min:0'],
            'payment_method' => ['required', Rule::in(['cash', 'qris', 'transfer'])],
            'amount_paid' => ['required_if:payment_method,cash', 'nullable', 'integer', 'min:0'],
        ]);

        $discountType = $data['discount_type'] ?? 'none';
        $discountValue = $discountType === 'none' ? 0 : (int) ($data['discount_value'] ?? 0);

        if ($discountType === 'percent' && $discountValue > 100) {
            throw ValidationException::withMessages([
                'discount_value' => ['Diskon persen maksimal 100.'],
            ]);
        }

        // Gabungkan item dengan produk yang sama
        $quantities = collect($data['items'])
            ->groupBy('product_id')
            ->map(fn ($group) => (int) $group->sum('quantity'));

        $transaction = DB::transaction(function () use ($request, $data, $quantities, $discountType, $discountValue) {
            $products = Product::whereIn('id', $quantities->keys()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $lines = [];

            foreach ($quantities as $productId => $qty) {
                $product = $products->get((int) $productId);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => ['Salah satu produk sudah tidak tersedia.'],
                    ]);
                }

                if ($product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok {$product->name} tidak mencukupi (tersisa {$product->stock})."],
                    ]);
                }

                $lineSubtotal = $product->price_sell * $qty;
                $subtotal += $lineSubtotal;
                $lines[] = [$product, $qty, $lineSubtotal];
            }

            $discountAmount = match ($discountType) {
                'nominal' => $discountValue,
                'percent' => (int) round($subtotal * $discountValue / 100),
                default => 0,
            };

            if ($discountAmount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount_value' => ['Diskon melebihi subtotal.'],
                ]);
            }

            $total = $subtotal - $discountAmount;

            if ($data['payment_method'] === 'cash') {
                $amountPaid = (int) $data['amount_paid'];

                if ($amountPaid < $total) {
                    throw ValidationException::withMessages([
                        'amount_paid' => ['Uang yang diterima kurang dari total.'],
                    ]);
                }

                $change = $amountPaid - $total;
            } else {
                $amountPaid = $total;
                $change = 0;
            }

            $transaction = Transaction::create([
                'invoice_no' => $this->nextInvoiceNo(),
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'payment_method' => $data['payment_method'],
                'amount_paid' => $amountPaid,
                'change_amount' => $change,
                'status' => 'completed',
            ]);

            foreach ($lines as [$product, $qty, $lineSubtotal]) {
                $transaction->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price_buy' => $product->price_buy,
                    'price_sell' => $product->price_sell,
                    'quantity' => $qty,
                    'subtotal' => $lineSubtotal,
                ]);

                $product->stock -= $qty;
                $product->save();

                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'transaction_id' => $transaction->id,
                    'type' => 'out',
                    'quantity' => -$qty,
                    'stock_after' => $product->stock,
                    'note' => 'Penjualan '.$transaction->invoice_no,
                ]);
            }

            return $transaction;
        });

        return response()->json([
            'message' => 'Transaksi berhasil.',
            'data' => $this->present($request, $transaction),
        ], 201);
    }

    public function receipt(Request $request, Transaction $transaction): JsonResponse
    {
        $this->ensureCanView($request, $transaction);

        $transaction->loadMissing(['items', 'user:id,name']);
        $store = StoreSetting::first();

        return response()->json([
            'data' => [
                'store' => [
                    'name' => $store?->store_name ?? 'Kasirku',
                    'address' => $store?->address,
                    'phone' => $store?->phone,
                ],
                'invoice_no' => $transaction->invoice_no,
                'date' => $transaction->created_at->format('d/m/Y H:i'),
                'cashier' => $transaction->user->name,
                'status' => $transaction->status,
                'items' => $transaction->items->map(fn ($item) => [
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'price' => $item->price_sell,
                    'subtotal' => $item->subtotal,
                ])->values(),
                'subtotal' => $transaction->subtotal,
                'discount' => $transaction->discount_amount,
                'total' => $transaction->total,
                'payment_method' => $transaction->payment_method,
                'amount_paid' => $transaction->amount_paid,
                'change' => $transaction->change_amount,
                'footer' => $store?->receipt_footer,
            ],
        ]);
    }

    public function void(Request $request, Transaction $transaction): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $transaction, $data) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'voided') {
                throw ValidationException::withMessages([
                    'transaction' => ['Transaksi ini sudah dibatalkan.'],
                ]);
            }

            $items = $locked->items()->orderBy('product_id')->get();

            foreach ($items as $item) {
                $product = Product::withTrashed()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                $product->stock += $item->quantity;
                $product->save();

                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'transaction_id' => $locked->id,
                    'type' => 'void_return',
                    'quantity' => $item->quantity,
                    'stock_after' => $product->stock,
                    'note' => 'Void '.$locked->invoice_no,
                ]);
            }

            $locked->update([
                'status' => 'voided',
                'voided_by' => $request->user()->id,
                'void_reason' => $data['reason'],
                'voided_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan.',
            'data' => $this->present($request, $transaction->refresh()),
        ]);
    }

    private function nextInvoiceNo(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';

        $last = Transaction::where('invoice_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('invoice_no')
            ->value('invoice_no');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function ensureCanView(Request $request, Transaction $transaction): void
    {
        $user = $request->user();

        abort_if(
            ! $user->isAdmin() && $transaction->user_id !== $user->id,
            403,
            'Anda tidak memiliki akses ke transaksi ini.'
        );
    }

    private function present(Request $request, Transaction $transaction): Transaction
    {
        $transaction->loadMissing(['items', 'user:id,name', 'voidedBy:id,name']);

        if (! $request->user()->isAdmin()) {
            $transaction->items->each->makeHidden('price_buy');
        }

        return $transaction;
    }
}