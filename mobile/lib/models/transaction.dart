class TransactionSummary {
  const TransactionSummary({
    required this.id,
    required this.invoiceNo,
    required this.createdAt,
    required this.cashier,
    required this.paymentMethod,
    required this.total,
    required this.status,
    required this.itemsCount,
  });

  final int id;
  final String invoiceNo;
  final String createdAt;
  final String cashier;
  final String paymentMethod;
  final int total;
  final String status;
  final int itemsCount;

  bool get isVoided => status == 'voided';

  factory TransactionSummary.fromJson(Map<String, dynamic> json) => TransactionSummary(
        id: json['id'] as int,
        invoiceNo: json['invoice_no'] as String,
        createdAt: json['created_at'] as String,
        cashier: (json['user'] as Map<String, dynamic>?)?['name'] as String? ?? '-',
        paymentMethod: json['payment_method'] as String,
        total: json['total'] as int,
        status: json['status'] as String,
        itemsCount: json['items_count'] as int? ?? 0,
      );
}