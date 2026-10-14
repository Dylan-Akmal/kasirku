import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/errors.dart';
import '../core/format.dart';
import '../providers/cart_provider.dart';
import '../widgets/receipt_dialog.dart';
import '../widgets/status_badge.dart';

class TransactionDetailScreen extends StatefulWidget {
  const TransactionDetailScreen({super.key, required this.transactionId});

  final int transactionId;

  @override
  State<TransactionDetailScreen> createState() => _TransactionDetailScreenState();
}

class _TransactionDetailScreenState extends State<TransactionDetailScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await ApiClient.instance.dio.get('/transactions/${widget.transactionId}');

      if (mounted) {
        setState(() => _data = response.data['data'] as Map<String, dynamic>);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _error = errorMessage(e, 'Gagal memuat detail transaksi.'));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _showReceipt() async {
    try {
      final response = await ApiClient.instance.dio.get(
        '/transactions/${widget.transactionId}/receipt',
      );

      final receipt = response.data['data'] as Map<String, dynamic>;

      if (!mounted) return;

      await showDialog<void>(
        context: context,
        builder: (_) => ReceiptDialog(
          result: CheckoutResult(
            invoiceNo: receipt['invoice_no'] as String,
            receipt: receipt,
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(errorMessage(e, 'Gagal memuat struk.'))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Detail Transaksi')),
      body: _buildBody(context),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null || _data == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error ?? 'Data tidak ditemukan.'),
            const SizedBox(height: 8),
            OutlinedButton(onPressed: _load, child: const Text('Coba lagi')),
          ],
        ),
      );
    }

    final d = _data!;
    final theme = Theme.of(context);
    final items = d['items'] as List<dynamic>;
    final voided = d['status'] == 'voided';
    final method = d['payment_method'] as String;
    final discount = d['discount_amount'] as int;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              d['invoice_no'] as String,
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            StatusBadge(voided: voided),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          dateTimeText(d['created_at'] as String),
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
        ),
        const SizedBox(height: 2),
        Text(
          'Kasir: ${(d['user'] as Map<String, dynamic>?)?['name'] ?? '-'}  ·  ${paymentLabel(method)}',
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
        ),
        const SizedBox(height: 16),
        Card(
          margin: EdgeInsets.zero,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              children: [
                for (final raw in items)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text((raw as Map<String, dynamic>)['product_name'] as String),
                              Text(
                                '${raw['quantity']} x ${rupiah(raw['price_sell'] as int)}',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: theme.colorScheme.onSurfaceVariant,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Text(rupiah(raw['subtotal'] as int)),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        _line('Subtotal', rupiah(d['subtotal'] as int)),
        if (discount > 0) _line('Diskon', '-${rupiah(discount)}', color: theme.colorScheme.error),
        _line('Total', rupiah(d['total'] as int), bold: true, large: true),
        _line('Dibayar', rupiah(d['amount_paid'] as int)),
        if (method == 'cash') _line('Kembalian', rupiah(d['change_amount'] as int)),
        if (voided) ...[
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: theme.colorScheme.errorContainer,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Transaksi dibatalkan',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: theme.colorScheme.onErrorContainer,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Oleh: ${(d['voided_by'] as Map<String, dynamic>?)?['name'] ?? '-'}',
                  style: TextStyle(color: theme.colorScheme.onErrorContainer),
                ),
                if (d['voided_at'] != null)
                  Text(
                    'Waktu: ${dateTimeText(d['voided_at'] as String)}',
                    style: TextStyle(color: theme.colorScheme.onErrorContainer),
                  ),
                Text(
                  'Alasan: ${d['void_reason'] ?? '-'}',
                  style: TextStyle(color: theme.colorScheme.onErrorContainer),
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 20),
        OutlinedButton.icon(
          onPressed: _showReceipt,
          icon: const Icon(Icons.receipt_long),
          label: const Text('Lihat Struk'),
        ),
      ],
    );
  }

  Widget _line(String label, String value, {bool bold = false, bool large = false, Color? color}) {
    final style = TextStyle(
      fontWeight: bold ? FontWeight.bold : FontWeight.normal,
      fontSize: large ? 18 : 14,
      color: color,
    );

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: style),
          Text(value, style: style),
        ],
      ),
    );
  }
}