import 'package:intl/intl.dart';

final NumberFormat _rupiahFormat = NumberFormat.currency(
  locale: 'id_ID',
  symbol: 'Rp ',
  decimalDigits: 0,
);

String rupiah(num value) => _rupiahFormat.format(value);

String dateTimeText(String iso) {
  return DateFormat('dd MMM yyyy, HH:mm', 'id_ID').format(DateTime.parse(iso).toLocal());
}

String paymentLabel(String method) {
  switch (method) {
    case 'cash':
      return 'Tunai';
    case 'qris':
      return 'QRIS';
    case 'transfer':
      return 'Transfer';
    default:
      return method;
  }
}