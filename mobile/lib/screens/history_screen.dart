import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/api_client.dart';
import '../core/errors.dart';
import '../core/format.dart';
import '../models/transaction.dart';
import '../widgets/status_badge.dart';
import 'transaction_detail_screen.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => HistoryScreenState();
}

class HistoryScreenState extends State<HistoryScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();
  Timer? _debounce;

  List<TransactionSummary> _items = [];
  int _page = 1;
  int _lastPage = 1;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  DateTimeRange? _range;
  String? _status;

  static final _apiDate = DateFormat('yyyy-MM-dd');

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  /// Dipanggil dari luar (misalnya saat tab Riwayat dibuka) untuk memuat ulang data.
  Future<void> refresh() => _load();

  void _onScroll() {
    if (!_scrollController.hasClients) return;

    final position = _scrollController.position;
    if (position.pixels >= position.maxScrollExtent - 200) {
      _load(reset: false);
    }
  }

  Future<void> _load({bool reset = true}) async {
    if (reset) {
      _page = 1;
      setState(() {
        _loading = true;
        _error = null;
      });
    } else {
      if (_loadingMore || _loading || _page >= _lastPage) return;
      _page += 1;
      setState(() => _loadingMore = true);
    }

    try {
      final term = _searchController.text.trim();

      final response = await ApiClient.instance.dio.get(
        '/transactions',
        queryParameters: {
          if (term.isNotEmpty) 'q': term,
          if (_status != null) 'status': _status,
          if (_range != null) 'from': _apiDate.format(_range!.start),
          if (_range != null) 'to': _apiDate.format(_range!.end),
          'page': _page,
          'per_page': 15,
        },
      );

      final body = response.data as Map<String, dynamic>;
      final list = (body['data'] as List<dynamic>)
          .map((e) => TransactionSummary.fromJson(e as Map<String, dynamic>))
          .toList();

      if (!mounted) return;

      setState(() {
        _items = reset ? list : [..._items, ...list];
        _lastPage = body['last_page'] as int;
      });
    } catch (e) {
      if (!reset) _page -= 1;

      if (mounted) {
        setState(() => _error = errorMessage(e, 'Gagal memuat riwayat transaksi.'));
      }
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
          _loadingMore = false;
        });
      }
    }
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), _load);
  }

  Future<void> _pickRange() async {
    final now = DateTime.now();

    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2024),
      lastDate: DateTime(now.year, now.month, now.day).add(const Duration(days: 1)),
      initialDateRange: _range,
    );

    if (picked != null) {
      setState(() => _range = picked);
      _load();
    }
  }

  void _setStatus(String? status) {
    setState(() => _status = status);
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final shortDate = DateFormat('dd MMM yyyy', 'id_ID');

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
          child: TextField(
            controller: _searchController,
            decoration: InputDecoration(
              hintText: 'Cari nomor invoice',
              prefixIcon: const Icon(Icons.search),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              isDense: true,
            ),
            onChanged: _onSearchChanged,
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _pickRange,
                  icon: const Icon(Icons.date_range, size: 18),
                  label: Text(
                    _range == null
                        ? 'Semua tanggal'
                        : '${shortDate.format(_range!.start)} - ${shortDate.format(_range!.end)}',
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ),
              if (_range != null)
                IconButton(
                  icon: const Icon(Icons.close),
                  tooltip: 'Hapus filter tanggal',
                  onPressed: () {
                    setState(() => _range = null);
                    _load();
                  },
                ),
            ],
          ),
        ),
        SizedBox(
          height: 48,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            children: [
              _statusChip('Semua', null),
              _statusChip('Selesai', 'completed'),
              _statusChip('Dibatalkan', 'voided'),
            ],
          ),
        ),
        Expanded(child: _buildList()),
      ],
    );
  }

  Widget _statusChip(String label, String? value) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label),
        selected: _status == value,
        onSelected: (_) => _setStatus(value),
      ),
    );
  }

  Widget _buildList() {
    if (_loading && _items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: () => _load(),
      child: _items.isEmpty
          ? ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                SizedBox(
                  height: 240,
                  child: Center(child: Text(_error ?? 'Tidak ada transaksi.')),
                ),
              ],
            )
          : ListView.builder(
              controller: _scrollController,
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.only(bottom: 12),
              itemCount: _items.length + (_loadingMore ? 1 : 0),
              itemBuilder: (context, index) {
                if (index >= _items.length) {
                  return const Padding(
                    padding: EdgeInsets.all(16),
                    child: Center(child: CircularProgressIndicator()),
                  );
                }

                return _tile(_items[index]);
              },
            ),
    );
  }

  Widget _tile(TransactionSummary t) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      child: ListTile(
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => TransactionDetailScreen(transactionId: t.id),
            ),
          );
        },
        title: Text(t.invoiceNo, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Text(
          '${dateTimeText(t.createdAt)}\n'
          '${paymentLabel(t.paymentMethod)} · ${t.itemsCount} item · ${t.cashier}',
        ),
        isThreeLine: true,
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(rupiah(t.total), style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            StatusBadge(voided: t.isVoided),
          ],
        ),
      ),
    );
  }
}