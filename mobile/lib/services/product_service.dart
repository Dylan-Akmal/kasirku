import '../core/api_client.dart';
import '../models/product.dart';

class ProductService {
  const ProductService._();

  /// Mencari satu produk berdasarkan SKU persis. Mengembalikan null bila tidak ditemukan.
  static Future<Product?> findBySku(String sku) async {
    final response = await ApiClient.instance.dio.get(
      '/products',
      queryParameters: {'sku': sku},
    );

    final list = response.data['data'] as List<dynamic>;

    if (list.length != 1) return null;

    return Product.fromJson(list.first as Map<String, dynamic>);
  }
}