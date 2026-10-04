class PackageInfo {
  final int id;
  final String name;
  final double unitsPerPackage;

  const PackageInfo({
    required this.id,
    required this.name,
    required this.unitsPerPackage,
  });

  factory PackageInfo.fromJson(Map<String, dynamic> json) {
    return PackageInfo(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? 'dona',
      unitsPerPackage: (json['units_per_package'] as num?)?.toDouble() ?? 1.0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'units_per_package': unitsPerPackage,
    };
  }
}

class ProductVariant {
  final int id;
  final int productId;
  final String sku;
  final double litres;
  final int volumeMl;
  final String displayVolume;
  final int stockQty;
  final int? costPrice;
  final int? averageCost;
  final int retailPrice;
  final int defaultSalePrice;
  final List<PackageInfo> packages;

  const ProductVariant({
    required this.id,
    required this.productId,
    this.sku = '',
    required this.litres,
    this.volumeMl = 500,
    this.displayVolume = '',
    required this.stockQty,
    this.costPrice,
    this.averageCost,
    required this.retailPrice,
    required this.defaultSalePrice,
    this.packages = const [],
  });

  factory ProductVariant.fromJson(Map<String, dynamic> json) {
    final litresVal = (json['litres'] as num?)?.toDouble() ?? 0.5;
    final costVal = json['cost_price'] != null
        ? (json['cost_price'] as num).toInt()
        : (json['average_cost'] != null
            ? (json['average_cost'] as num).toInt()
            : null);
    final saleVal = json['default_sale_price'] != null
        ? (json['default_sale_price'] as num).toInt()
        : (json['sale_price'] != null
            ? (json['sale_price'] as num).toInt()
            : ((json['retail_price'] as num?)?.toInt() ?? 0));

    return ProductVariant(
      id: json['id'] as int? ?? 0,
      productId: json['product_id'] as int? ?? 0,
      sku: json['sku'] as String? ?? '',
      litres: litresVal,
      volumeMl: json['volume_ml'] as int? ?? (litresVal * 1000).toInt(),
      displayVolume: json['display_volume'] as String? ??
          (litresVal == litresVal.roundToDouble()
              ? '${litresVal.toInt()} L'
              : '$litresVal L'),
      stockQty: ((json['stock_qty'] ?? json['stock_quantity'] ?? json['current_stock']) as num?)?.toInt() ?? 0,
      costPrice: costVal,
      averageCost: costVal,
      retailPrice: saleVal,
      defaultSalePrice: saleVal,
      packages: (json['packages'] as List<dynamic>?)
              ?.map((e) => PackageInfo.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  int get stockQuantity => stockQty;
  int get salePrice => defaultSalePrice;

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'product_id': productId,
      'sku': sku,
      'litres': litres,
      'volume_ml': volumeMl,
      'display_volume': displayVolume,
      'stock_qty': stockQty,
      'cost_price': costPrice,
      'average_cost': averageCost,
      'retail_price': retailPrice,
      'default_sale_price': defaultSalePrice,
      'packages': packages.map((p) => p.toJson()).toList(),
    };
  }
}

class Product {
  final int id;
  final String name;
  final String code;
  final List<ProductVariant> variants;

  const Product({
    required this.id,
    required this.name,
    this.code = '',
    required this.variants,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      code: json['code'] as String? ?? '',
      variants: (json['variants'] as List<dynamic>?)
              ?.map((e) => ProductVariant.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'code': code,
      'variants': variants.map((v) => v.toJson()).toList(),
    };
  }
}
