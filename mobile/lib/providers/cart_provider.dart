import 'package:flutter/foundation.dart';

import '../core/api_client.dart';
import '../models/product.dart';

enum DiscountType { none, nominal, percent }

enum PaymentMethod {
  cash('cash', 'Tunai'),
  qris('qris', 'QRIS'),
  transfer('transfer', 'Transfer');

  const PaymentMethod(this.apiValue, this.label);

  final String apiValue;
  final String label;
}

class CartItem {
  CartItem({required this.product, this.quantity = 1});

  final Product product;
  int quantity;

  int get subtotal => product.priceSell * quantity;
}

class CheckoutResult {
  const CheckoutResult({required this.invoiceNo, this.receipt});

  final String invoiceNo;
  final Map<String, dynamic>? receipt;
}

class CartProvider extends ChangeNotifier {
  final List<CartItem> items = [];

  DiscountType discountType = DiscountType.none;
  int discountValue = 0;
  PaymentMethod paymentMethod = PaymentMethod.cash;
  int amountPaid = 0;

  int get itemCount => items.fold(0, (sum, item) => sum + item.quantity);

  int get subtotal => items.fold(0, (sum, item) => sum + item.subtotal);

  bool get discountInvalid {
    switch (discountType) {
      case DiscountType.nominal:
        return discountValue > subtotal;
      case DiscountType.percent:
        return discountValue > 100;
      case DiscountType.none:
        return false;
    }
  }

  int get discountAmount {
    if (discountInvalid) return 0;

    switch (discountType) {
      case DiscountType.nominal:
        return discountValue;
      case DiscountType.percent:
        return (subtotal * discountValue / 100).round();
      case DiscountType.none:
        return 0;
    }
  }

  int get total => subtotal - discountAmount;

  int get paid => paymentMethod == PaymentMethod.cash ? amountPaid : total;

  int get change => paid - total;

  bool get canPay =>
      items.isNotEmpty &&
      !discountInvalid &&
      (paymentMethod != PaymentMethod.cash || paid >= total);

  /// Mengembalikan pesan peringatan bila gagal, atau null bila berhasil.
  String? add(Product product) {
    if (product.stock <= 0) return '${product.name} stok habis.';

    final index = items.indexWhere((item) => item.product.id == product.id);

    if (index >= 0) {
      final current = items[index];

      if (current.quantity + 1 > product.stock) {
        return 'Stok ${product.name} hanya ${product.stock}.';
      }

      items[index] = CartItem(product: product, quantity: current.quantity + 1);
    } else {
      items.add(CartItem(product: product));
    }

    notifyListeners();
    return null;
  }

  String? changeQuantity(CartItem item, int delta) {
    final next = item.quantity + delta;

    if (next < 1) {
      items.remove(item);
      notifyListeners();
      return null;
    }

    if (next > item.product.stock) {
      return 'Stok ${item.product.name} hanya ${item.product.stock}.';
    }

    item.quantity = next;
    notifyListeners();
    return null;
  }

  void remove(CartItem item) {
    items.remove(item);
    notifyListeners();
  }

  void setDiscountType(DiscountType type) {
    discountType = type;
    if (type == DiscountType.none) discountValue = 0;
    notifyListeners();
  }

  void setDiscountValue(int value) {
    discountValue = value;
    notifyListeners();
  }

  void setPaymentMethod(PaymentMethod method) {
    paymentMethod = method;
    notifyListeners();
  }

  void setAmountPaid(int value) {
    amountPaid = value;
    notifyListeners();
  }

  void clear() {
    items.clear();
    discountType = DiscountType.none;
    discountValue = 0;
    amountPaid = 0;
    notifyListeners();
  }

  Future<CheckoutResult> checkout() async {
    final dio = ApiClient.instance.dio;

    final payload = <String, dynamic>{
      'items': items
          .map((item) => {'product_id': item.product.id, 'quantity': item.quantity})
          .toList(),
      'discount_type': discountType.name,
      'discount_value': discountType == DiscountType.none ? 0 : discountValue,
      'payment_method': paymentMethod.apiValue,
    };

    if (paymentMethod == PaymentMethod.cash) {
      payload['amount_paid'] = paid;
    }

    final response = await dio.post('/transactions', data: payload);
    final created = response.data['data'] as Map<String, dynamic>;

    // Transaksi sudah tercatat: kosongkan keranjang agar tidak terkirim dua kali.
    clear();

    Map<String, dynamic>? receipt;
    try {
      final result = await dio.get('/transactions/${created['id']}/receipt');
      receipt = result.data['data'] as Map<String, dynamic>;
    } catch (_) {
      // struk gagal dimuat, transaksi tetap berhasil
    }

    return CheckoutResult(
      invoiceNo: created['invoice_no'] as String,
      receipt: receipt,
    );
  }
}