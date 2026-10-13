import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../core/errors.dart';
import '../core/format.dart';
import '../providers/cart_provider.dart';

class CartSheet extends StatefulWidget {
  const CartSheet({super.key, required this.onPaid});

  final Future<void> Function(CheckoutResult result) onPaid;

  @override
  State<CartSheet> createState() => _CartSheetState();
}

class _CartSheetState extends State<CartSheet> {
  final _discountController = TextEditingController();
  final _paidController = TextEditingController();

  bool _submitting = false;
  String? _error;
  String? _notice;

  static const _quickAmounts = [20000, 50000, 100000];

  @override
  void dispose() {
    _discountController.dispose();
    _paidController.dispose();
    super.dispose();
  }

  void _showNotice(String? message) {
    if (message != null) {
      setState(() => _notice = message);
    } else if (_notice != null) {
      setState(() => _notice = null);
    }
  }

  Future<void> _pay() async {
    final cart = context.read<CartProvider>();

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final result = await cart.checkout();

      if (!mounted) return;

      Navigator.of(context).pop();
      widget.onPaid(result);
    } catch (e) {
      if (mounted) {
        setState(() => _error = errorMessage(e, 'Transaksi gagal. Coba lagi.'));
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final theme = Theme.of(context);

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Keranjang', style: theme.textTheme.titleLarge),
                if (cart.items.isNotEmpty)
                  TextButton(
                    onPressed: () {
                      cart.clear();
                      _discountController.clear();
                      _paidController.clear();
                    },
                    child: const Text('Kosongkan'),
                  ),
              ],
            ),
            if (_notice != null)
              Container(
                margin: const EdgeInsets.only(top: 8),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: theme.colorScheme.tertiaryContainer,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(_notice!),
              ),
            if (cart.items.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 32),
                child: Center(child: Text('Keranjang kosong.')),
              )
            else ...[
              const SizedBox(height: 8),
              for (final item in cart.items)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(item.product.name, style: theme.textTheme.bodyLarge),
                            Text(
                              rupiah(item.product.priceSell),
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.remove_circle_outline),
                        onPressed: () => _showNotice(cart.changeQuantity(item, -1)),
                      ),
                      Text('${item.quantity}', style: theme.textTheme.titleMedium),
                      IconButton(
                        icon: const Icon(Icons.add_circle_outline),
                        onPressed: () => _showNotice(cart.changeQuantity(item, 1)),
                      ),
                      SizedBox(
                        width: 84,
                        child: Text(
                          rupiah(item.subtotal),
                          textAlign: TextAlign.right,
                          style: theme.textTheme.titleSmall,
                        ),
                      ),
                    ],
                  ),
                ),
              const Divider(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Subtotal'),
                  Text(rupiah(cart.subtotal)),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  DropdownButton<DiscountType>(
                    value: cart.discountType,
                    items: const [
                      DropdownMenuItem(value: DiscountType.none, child: Text('Tanpa diskon')),
                      DropdownMenuItem(value: DiscountType.nominal, child: Text('Diskon Rp')),
                      DropdownMenuItem(value: DiscountType.percent, child: Text('Diskon %')),
                    ],
                    onChanged: (value) {
                      if (value == null) return;
                      cart.setDiscountType(value);
                      _discountController.clear();
                    },
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: _discountController,
                      enabled: cart.discountType != DiscountType.none,
                      keyboardType: TextInputType.number,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      decoration: const InputDecoration(
                        isDense: true,
                        border: OutlineInputBorder(),
                        hintText: '0',
                      ),
                      onChanged: (value) => cart.setDiscountValue(int.tryParse(value) ?? 0),
                    ),
                  ),
                ],
              ),
              if (cart.discountInvalid)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    'Diskon melebihi ${cart.discountType == DiscountType.percent ? '100%' : 'subtotal'}.',
                    style: TextStyle(color: theme.colorScheme.error, fontSize: 12),
                  ),
                ),
              if (cart.discountAmount > 0)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Diskon'),
                      Text('-${rupiah(cart.discountAmount)}',
                          style: TextStyle(color: theme.colorScheme.error)),
                    ],
                  ),
                ),
              const SizedBox(height: 12),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Total', style: theme.textTheme.titleMedium),
                  Text(
                    rupiah(cart.total),
                    style: theme.textTheme.headlineSmall?.copyWith(
                      color: theme.colorScheme.primary,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              SegmentedButton<PaymentMethod>(
                segments: [
                  for (final method in PaymentMethod.values)
                    ButtonSegment(value: method, label: Text(method.label)),
                ],
                selected: {cart.paymentMethod},
                onSelectionChanged: (selection) => cart.setPaymentMethod(selection.first),
              ),
              if (cart.paymentMethod == PaymentMethod.cash) ...[
                const SizedBox(height: 12),
                TextField(
                  controller: _paidController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Uang diterima',
                    border: OutlineInputBorder(),
                  ),
                  onChanged: (value) => cart.setAmountPaid(int.tryParse(value) ?? 0),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  children: [
                    ActionChip(
                      label: const Text('Uang pas'),
                      onPressed: () {
                        _paidController.text = '${cart.total}';
                        cart.setAmountPaid(cart.total);
                      },
                    ),
                    for (final amount in _quickAmounts)
                      ActionChip(
                        label: Text(rupiah(amount)),
                        onPressed: () {
                          _paidController.text = '$amount';
                          cart.setAmountPaid(amount);
                        },
                      ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(cart.change >= 0 ? 'Kembalian' : 'Kurang'),
                    Text(
                      rupiah(cart.change.abs()),
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        color: cart.change >= 0 ? Colors.green.shade700 : theme.colorScheme.error,
                      ),
                    ),
                  ],
                ),
              ],
              if (_error != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.errorContainer,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    _error!,
                    style: TextStyle(color: theme.colorScheme.onErrorContainer),
                  ),
                ),
              ],
              const SizedBox(height: 16),
              FilledButton(
                onPressed: (cart.canPay && !_submitting) ? _pay : null,
                style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
                child: _submitting
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text('Bayar ${rupiah(cart.total)}'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}