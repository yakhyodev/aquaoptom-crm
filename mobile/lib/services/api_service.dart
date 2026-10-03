import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/product.dart';
import '../models/sale.dart';

class ApiService {
  // Standart Laravel API manzili (port: 3003)
  static String baseUrl = 'http://127.0.0.1:3003/api';

  // Local keshlangan ma'lumotlar (agar server ulanmagan bo'lsa darhol ishlashi uchun)
  static List<Product> cachedProducts = [
    Product(
      id: 1,
      name: 'Fanta',
      variants: [
        ProductVariant(id: 1, productId: 1, litres: 0.5, stockQty: 150, costPrice: 5000, retailPrice: 6500),
        ProductVariant(id: 2, productId: 1, litres: 1.0, stockQty: 80, costPrice: 7500, retailPrice: 9500),
        ProductVariant(id: 3, productId: 1, litres: 1.5, stockQty: 200, costPrice: 10000, retailPrice: 12500),
      ],
    ),
    Product(
      id: 2,
      name: 'Coca-Cola',
      variants: [
        ProductVariant(id: 4, productId: 2, litres: 0.5, stockQty: 240, costPrice: 5200, retailPrice: 6500),
        ProductVariant(id: 5, productId: 2, litres: 1.5, stockQty: 180, costPrice: 10500, retailPrice: 13000),
      ],
    ),
    Product(
      id: 3,
      name: 'Chortoq',
      variants: [
        ProductVariant(id: 6, productId: 3, litres: 0.5, stockQty: 300, costPrice: 4000, retailPrice: 5500),
        ProductVariant(id: 7, productId: 3, litres: 1.0, stockQty: 120, costPrice: 6000, retailPrice: 8000),
      ],
    ),
    Product(
      id: 4,
      name: 'Nestle Pure Life',
      variants: [
        ProductVariant(id: 8, productId: 4, litres: 5.0, stockQty: 90, costPrice: 9000, retailPrice: 12000),
        ProductVariant(id: 9, productId: 4, litres: 18.9, stockQty: 50, costPrice: 14000, retailPrice: 20000),
      ],
    ),
  ];

  static Future<List<Product>> getProducts() async {
    try {
      final res = await http.get(Uri.parse('$baseUrl/products')).timeout(const Duration(seconds: 3));
      if (res.statusCode == 200) {
        final data = json.decode(res.body)['data'] as List;
        cachedProducts = data.map((json) => Product.fromJson(json)).toList();
      }
    } catch (_) {
      // Server ulanmasa lokal keshdan qaytaradi
    }
    return cachedProducts;
  }

  static Future<bool> quickInward({
    required String name,
    required double litres,
    required int quantity,
    required double costPrice,
  }) async {
    try {
      final res = await http.post(
        Uri.parse('$baseUrl/inward'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'name': name,
          'litres': litres,
          'quantity': quantity,
          'cost_price': costPrice,
        }),
      ).timeout(const Duration(seconds: 3));

      if (res.statusCode == 200 || res.statusCode == 201) {
        await getProducts();
        return true;
      }
    } catch (_) {}

    // Lokal simulyatsiya (offline rejimda ishlash)
    var prod = cachedProducts.firstWhere(
      (p) => p.name.toLowerCase() == name.toLowerCase(),
      orElse: () {
        final newP = Product(id: cachedProducts.length + 1, name: name, variants: []);
        cachedProducts.add(newP);
        return newP;
      },
    );

    var variantIndex = prod.variants.indexWhere((v) => v.litres == litres);
    if (variantIndex != -1) {
      final old = prod.variants[variantIndex];
      final newTotalCost = (old.stockQty * old.costPrice) + (quantity * costPrice);
      final newStock = old.stockQty + quantity;
      final newCost = (newTotalCost / newStock).roundToDouble();

      prod.variants[variantIndex] = ProductVariant(
        id: old.id,
        productId: prod.id,
        litres: litres,
        stockQty: newStock,
        costPrice: newCost,
        retailPrice: old.retailPrice,
      );
    } else {
      prod.variants.add(
        ProductVariant(
          id: DateTime.now().millisecondsSinceEpoch % 10000,
          productId: prod.id,
          litres: litres,
          stockQty: quantity,
          costPrice: costPrice,
          retailPrice: (costPrice * 1.25).roundToDouble(),
        ),
      );
    }
    return true;
  }

  static Future<bool> checkoutSale({
    required String customerName,
    required List<CartItem> items,
    required String paymentType,
  }) async {
    try {
      final res = await http.post(
        Uri.parse('$baseUrl/sales'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'customer_name': customerName,
          'payment_type': paymentType,
          'items': items.map((i) => i.toJson()).toList(),
        }),
      ).timeout(const Duration(seconds: 3));

      if (res.statusCode == 200 || res.statusCode == 201) {
        await getProducts();
        return true;
      }
    } catch (_) {}

    // Lokal qoldiqlarni kamaytirish
    for (var cartItem in items) {
      for (var p in cachedProducts) {
        for (var i = 0; i < p.variants.length; i++) {
          if (p.variants[i].id == cartItem.variantId) {
            final old = p.variants[i];
            p.variants[i] = ProductVariant(
              id: old.id,
              productId: old.productId,
              litres: old.litres,
              stockQty: (old.stockQty - cartItem.quantity).clamp(0, 999999),
              costPrice: old.costPrice,
              retailPrice: old.retailPrice,
            );
          }
        }
      }
    }
    return true;
  }
}
