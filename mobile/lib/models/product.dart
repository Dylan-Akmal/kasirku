class ProductCategory {
  const ProductCategory({required this.id, required this.name});

  final int id;
  final String name;

  factory ProductCategory.fromJson(Map<String, dynamic> json) => ProductCategory(
        id: json['id'] as int,
        name: json['name'] as String,
      );
}

class Product {
  const Product({
    required this.id,
    required this.name,
    required this.priceSell,
    required this.stock,
    required this.minStock,
    this.sku,
    this.categoryName,
    this.imageUrl,
  });

  final int id;
  final String name;
  final String? sku;
  final String? categoryName;
  final int priceSell;
  final int stock;
  final int minStock;
  final String? imageUrl;

  bool get isLowStock => stock <= minStock;

  factory Product.fromJson(Map<String, dynamic> json) => Product(
        id: json['id'] as int,
        name: json['name'] as String,
        sku: json['sku'] as String?,
        categoryName: (json['category'] as Map<String, dynamic>?)?['name'] as String?,
        priceSell: json['price_sell'] as int,
        stock: json['stock'] as int,
        minStock: json['min_stock'] as int,
        imageUrl: json['image_url'] as String?,
      );
}