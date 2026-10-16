import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../core/errors.dart';
import '../core/format.dart';
import '../models/product.dart';
import '../providers/cart_provider.dart';
import '../services/product_service.dart';
import '../widgets/cart_sheet.dart';
import '../widgets/receipt_dialog.dart';
import 'scanner_screen.dart';

class CashierScreen extends StatefulWidget {
  const CashierScreen({super.key});

  @override
  State<CashierScreen> createState() => _CashierScreenState();
}

class _CashierScreenState extends State<CashierScreen> {
  final _searchController = TextEditingController();
  Timer? _debounce;

  List<Product> _products = [];
  List<ProductCategory> _categories = [];
  int? _categoryId;

  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadCategories();
    _loadProducts();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadCategories() async {
    try {
      final response = await ApiClient.instance.dio.get('/categories');
      final list = (response.data['data'] as List<dynamic>)
          .map((e) => ProductCategory.fromJson(e as Map<String, dynamic>))
          .toList();

      if (mounted) setState(() => _categories = list);
    } catch (_) {
      // filter kategori tetap kosong bila gagal
    }
  }

  Future<void> _loadProducts() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final term = _searchController.text.trim();

      final response = await ApiClient.instance.dio.get(
        '/products',
        queryParameters: {
          if (term.isNotEmpty) 'q': term,
          if (_categoryId != null) 'category_id': _categoryId,
          'per_page': 30,
        },
      );

      final list = (response.data['data'] as List<dynamic>)
          .map((e) => Product.fromJson(e as Map<String, dynamic>))
          .toList();

      if (mounted) setState(() => _products = list);
    } catch (e) {
      if (mounted) setState(() => _error = errorMessage(e, 'Gagal memuat produk.'));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), _loadProducts);
  }

  /// Menekan tombol cari di keyboard dengan SKU persis langsung memasukkan produk ke keranjang.
  Future<void> _onSearchSubmitted(String value) async {
    final term = value.trim();
    if (term.isEmpty) return;

    try {
      final product = await ProductService.findBySku(term);

      if (!mounted) return;

      if (product == null) {
        _toast('SKU "$term" tidak ditemukan.');
        return;
      }

      _toast(context.read<CartProvider>().add(product) ?? '${product.name} ditambahkan.');
      _searchController.clear();
      _loadProducts();
    } catch (e) {
      _toast(errorMessage(e, 'Gagal mencari SKU.'));
    }
  }

  Future<void> _openScanner() async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const ScannerScreen()),
    );

    if (mounted) _loadProducts();
  }

  void _toast(String message) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(content: Text(message), duration: const Duration(seconds: 2)),
      );
  }

  void _addProduct(Product product) {
    final warning = context.read<CartProvider>().add(product);
    if (warning != null) _toast(warning);
  }

  void _openCart() {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => CartSheet(onPaid: _onPaid),
    );
  }

  Future<void> _onPaid(CheckoutResult result) async {
    _loadProducts();

    if (!mounted) return;

    await showDialog<void>(
      context: context,
      builder: (_) => ReceiptDialog(result: result),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
          child: TextField(
            controller: _searchController,
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
              hintText: 'Cari produk atau ketik SKU lalu cari',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(
                icon: const Icon(Icons.qr_code_scanner),
                tooltip: 'Scan barcode',
                onPressed: _openScanner,
              ),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              isDense: true,
            ),
            onChanged: _onSearchChanged,
            onSubmitted: _onSearchSubmitted,
          ),
        ),
        SizedBox(
          height: 48,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: const Text('Semua'),
                  selected: _categoryId == null,
                  onSelected: (_) {
                    setState(() => _categoryId = null);
                    _loadProducts();
                  },
                ),
              ),
              for (final category in _categories)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(category.name),
                    selected: _categoryId == category.id,
                    onSelected: (_) {
                      setState(() => _categoryId = category.id);
                      _loadProducts();
                    },
                  ),
                ),
            ],
          ),
        ),
        Expanded(child: _buildGrid()),
        Consumer<CartProvider>(
          builder: (context, cart, _) {
            if (cart.items.isEmpty) return const SizedBox.shrink();

            return SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
                child: SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: _openCart,
                    icon: const Icon(Icons.shopping_cart),
                    style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
                    label: Text('Keranjang · ${cart.itemCount} item · ${rupiah(cart.subtotal)}'),
                  ),
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _buildGrid() {
    if (_loading && _products.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && _products.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!),
            const SizedBox(height: 8),
            OutlinedButton(onPressed: _loadProducts, child: const Text('Coba lagi')),
          ],
        ),
      );
    }

    if (_products.isEmpty) {
      return const Center(child: Text('Produk tidak ditemukan.'));
    }

    return RefreshIndicator(
      onRefresh: _loadProducts,
      child: GridView.builder(
        padding: const EdgeInsets.all(12),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 0.82,
        ),
        itemCount: _products.length,
        itemBuilder: (context, index) {
          final product = _products[index];
          return _ProductCard(product: product, onTap: () => _addProduct(product));
        },
      ),
    );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.product, required this.onTap});

  final Product product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final soldOut = product.stock <= 0;

    return Opacity(
      opacity: soldOut ? 0.5 : 1,
      child: Card(
        clipBehavior: Clip.antiAlias,
        margin: EdgeInsets.zero,
        child: InkWell(
          onTap: soldOut ? null : onTap,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: SizedBox(
                  width: double.infinity,
                  child: product.imageUrl != null
                      ? Image.network(
                          product.imageUrl!,
                          fit: BoxFit.cover,
                          errorBuilder: (_, _, _) => _initial(theme),
                        )
                      : _initial(theme),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      rupiah(product.priceSell),
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.primary,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Text(
                      soldOut ? 'Stok habis' : 'Stok ${product.stock}',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: product.isLowStock
                            ? theme.colorScheme.error
                            : theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _initial(ThemeData theme) {
    return Container(
      color: theme.colorScheme.surfaceContainerHighest,
      alignment: Alignment.center,
      child: Text(
        product.name.isEmpty ? '?' : product.name[0].toUpperCase(),
        style: theme.textTheme.headlineMedium?.copyWith(
          color: theme.colorScheme.outline,
        ),
      ),
    );
  }
}