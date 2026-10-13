import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/format.dart';
import '../providers/cart_provider.dart';

const int _width = 32;

String _row(String left, String right) {
  final space = _width - left.length - right.length;
  return '$left${' ' * (space > 0 ? space : 1)}$right';
}

String _center(String text) {
  final space = (_width - text.length) ~/ 2;
  return '${' ' * (space > 0 ? space : 0)}$text';
}

String buildReceiptText(Map<String, dynamic> receipt) {
  final store = receipt['store'] as Map<String, dynamic>;
  final items = receipt['items'] as List<dynamic>;
  final dash = '-' * _width;
  final method = receipt['payment_method'] as String;

  final labels = {'cash': 'Tunai', 'qris': 'QRIS', 'transfer': 'Transfer'};

  final lines = <String>[
    _center(store['name'] as String),
    if (store['address'] != null) _center(store['address'] as String),
    if (store['phone'] != null) _center(store['phone'] as String),
    dash,
    'No   : ${receipt['invoice_no']}',
    'Tgl  : ${receipt['date']}',
    'Kasir: ${receipt['cashier']}',
    if (receipt['status'] == 'voided') '*** DIBATALKAN ***',
    dash,
  ];

  for (final raw in items) {
    final item = raw as Map<String, dynamic>;
    lines.add(item['name'] as String);
    lines.add(_row(
      '${item['quantity']} x ${rupiah(item['price'] as int)}',
      rupiah(item['subtotal'] as int),
    ));
  }

  lines.add(dash);
  lines.add(_row('Subtotal', rupiah(receipt['subtotal'] as int)));

  if ((receipt['discount'] as int) > 0) {
    lines.add(_row('Diskon', '-${rupiah(receipt['discount'] as int)}'));
  }

  lines.add(_row('Total', rupiah(receipt['total'] as int)));
  lines.add(_row('Bayar (${labels[method]})', rupiah(receipt['amount_paid'] as int)));

  if (method == 'cash') {
    lines.add(_row('Kembali', rupiah(receipt['change'] as int)));
  }

  if (receipt['footer'] != null) {
    lines.add('');
    lines.add(_center(receipt['footer'] as String));
  }

  return lines.join('\n');
}

class ReceiptDialog extends StatelessWidget {
  const ReceiptDialog({super.key, required this.result});

  final CheckoutResult result;

  @override
  Widget build(BuildContext context) {
    final receipt = result.receipt;
    final text = receipt == null ? null : buildReceiptText(receipt);

    return AlertDialog(
      title: const Text('Struk Transaksi'),
      content: SingleChildScrollView(
        scrollDirection: Axis.vertical,
        child: SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Text(
            text ?? 'Transaksi ${result.invoiceNo} berhasil, tetapi struk gagal dimuat.',
            style: const TextStyle(fontFamily: 'monospace', fontSize: 12.5, height: 1.4),
          ),
        ),
      ),
      actions: [
        if (text != null)
          TextButton(
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: text));

              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Struk disalin ke clipboard.')),
                );
              }
            },
            child: const Text('Salin'),
          ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Selesai'),
        ),
      ],
    );
  }
}