<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $itemProfitByDay = $this->itemQuery($from, $to)
            ->selectRaw('DATE(transactions.created_at) as day, SUM((CAST(transaction_items.price_sell AS SIGNED) - CAST(transaction_items.price_buy AS SIGNED)) * transaction_items.quantity) as profit')
            ->groupByRaw('DATE(transactions.created_at)')
            ->pluck('profit', 'day');

        $days = $this->completed($from, $to)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as transaction_count, SUM(total) as revenue, SUM(discount_amount) as discount')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->day,
                'transaction_count' => (int) $row->transaction_count,
                'revenue' => (int) $row->revenue,
                'discount' => (int) $row->discount,
                'gross_profit' => (int) ($itemProfitByDay[$row->day] ?? 0) - (int) $row->discount,
            ])
            ->values();

        $byPayment = $this->completed($from, $to)
            ->selectRaw('payment_method, COUNT(*) as transaction_count, SUM(total) as revenue')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'payment_method' => $row->payment_method,
                'transaction_count' => (int) $row->transaction_count,
                'revenue' => (int) $row->revenue,
            ])
            ->values();

        $byCashier = $this->completed($from, $to)
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->selectRaw('users.name as cashier, COUNT(*) as transaction_count, SUM(transactions.total) as revenue')
            ->groupBy('transactions.user_id', 'users.name')
            ->get()
            ->map(fn ($row) => [
                'cashier' => $row->cashier,
                'transaction_count' => (int) $row->transaction_count,
                'revenue' => (int) $row->revenue,
            ])
            ->values();

        $voidedCount = Transaction::where('status', 'voided')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $count = (int) $days->sum('transaction_count');
        $revenue = (int) $days->sum('revenue');

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'summary' => [
                    'transaction_count' => $count,
                    'revenue' => $revenue,
                    'discount' => (int) $days->sum('discount'),
                    'gross_profit' => (int) $days->sum('gross_profit'),
                    'average_transaction' => $count > 0 ? intdiv($revenue, $count) : 0,
                    'voided_count' => $voidedCount,
                ],
                'by_day' => $days,
                'by_payment_method' => $byPayment,
                'by_cashier' => $byCashier,
            ],
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $limit = min(max((int) $request->query('limit', 10), 1), 50);
        $sort = $request->query('sort') === 'revenue' ? 'revenue' : 'quantity_sold';

        $rows = $this->itemQuery($from, $to)
            ->selectRaw('transaction_items.product_id, MAX(transaction_items.product_name) as product_name, SUM(transaction_items.quantity) as quantity_sold, SUM(transaction_items.subtotal) as revenue')
            ->groupBy('transaction_items.product_id')
            ->orderByDesc($sort)
            ->orderBy('transaction_items.product_id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => (int) $row->revenue,
            ])
            ->values();

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'products' => $rows,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);

        $filename = 'laporan-penjualan-'.$from->toDateString().'-sd-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');

            // BOM agar Excel membaca UTF-8 dengan benar
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Invoice', 'Tanggal', 'Kasir', 'Metode Bayar',
                'Subtotal', 'Diskon', 'Total', 'Dibayar', 'Kembalian',
            ]);

            $this->completed($from, $to)
                ->with('user:id,name')
                ->chunkById(500, function ($rows) use ($out) {
                    foreach ($rows as $transaction) {
                        fputcsv($out, [
                            $transaction->invoice_no,
                            $transaction->created_at->format('Y-m-d H:i:s'),
                            $transaction->user->name,
                            $transaction->payment_method,
                            $transaction->subtotal,
                            $transaction->discount_amount,
                            $transaction->total,
                            $transaction->amount_paid,
                            $transaction->change_amount,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function completed(Carbon $from, Carbon $to): Builder
    {
        return Transaction::query()
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$from, $to]);
    }

    private function itemQuery(Carbon $from, Carbon $to): Builder
    {
        return TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$from, $to]);
    }

    private function dateRange(Request $request): array
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $fromDate = $data['from'] ?? $data['to'] ?? now()->toDateString();
        $toDate = $data['to'] ?? $fromDate;

        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to' => ['Tanggal akhir tidak boleh sebelum tanggal awal.'],
            ]);
        }

        return [$from, $to];
    }
}