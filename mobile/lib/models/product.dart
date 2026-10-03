class ProductVariant {
  final int id;
  final int productId;
  final double litres;
  final int stockQty;
  final double costPrice;
  final double retailPrice;

  ProductVariant({
    required this.id,
    required this.productId,
    required this.litres,
    required this.stockQty,
    required this.costPrice,
    required this.retailPrice,
  });

  factory ProductVariant.fromJson(Map<String, dynamic> json) {
    return ProductVariant(
      id: json['id'] as int,
      productId: json['product_id'] as int,
      litres: (json['litres'] as num).toDouble(),
      stockQty: json['stock_qty'] as int? ?? 0,
      costPrice: (json['cost_price'] as num).toDouble(),
      retailPrice: (json['retail_price'] as num).toDouble(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'product_id': productId,
      'litres': litres,
      'stock_qty': stockQty,
      'cost_price': costPrice,
      'retail_price': retailPrice,
    };
  }
}

class Product {
  final int id;
  final String name;
  final List<ProductVariant> variants;

  Product({
    required this.id,
    required this.name,
    this.variants = const [],
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    var rawVariants = json['variants'] as List<dynamic>? ?? [];
    return Product(
      id: json['id'] as int,
      name: json['name'] as String,
      variants: rawVariants.map((v) => ProductVariant.fromJson(v as Map<String, dynamic>)).toList(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'variants': variants.map((v) => v.toJson()).toList(),
    };
  }
}
