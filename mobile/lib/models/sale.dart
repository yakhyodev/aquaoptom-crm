class CartItem {
  final int variantId;
  final String productName;
  final double litres;
  int quantity;
  final double costPrice;
  double retailPrice;
  bool isSystemPrice;
  final double defaultRetail;

  CartItem({
    required this.variantId,
    required this.productName,
    required this.litres,
    required this.quantity,
    required this.costPrice,
    required this.retailPrice,
    this.isSystemPrice = true,
    required this.defaultRetail,
  });

  double get totalPrice => quantity * retailPrice;
  double get totalCost => quantity * costPrice;
  double get profit => totalPrice - totalCost;

  Map<String, dynamic> toJson() {
    return {
      'variant_id': variantId,
      'quantity': quantity,
      'unit_cost': costPrice,
      'unit_price': retailPrice,
      'is_system_price': isSystemPrice,
    };
  }
}
