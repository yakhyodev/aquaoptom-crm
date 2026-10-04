class SaleItemRecord {
  final int id;
  final int variantId;
  final String productName;
  final String volumeName;
  final double quantity;
  final int salePrice;
  final int totalPrice;
  final bool isSystemPrice;

  const SaleItemRecord({
    required this.id,
    required this.variantId,
    required this.productName,
    this.volumeName = '',
    required this.quantity,
    required this.salePrice,
    required this.totalPrice,
    this.isSystemPrice = true,
  });

  factory SaleItemRecord.fromJson(Map<String, dynamic> json) {
    return SaleItemRecord(
      id: json['id'] as int? ?? 0,
      variantId: json['variant_id'] as int? ?? 0,
      productName: json['product_name'] as String? ?? 'Mahsulot',
      volumeName: json['volume_name'] as String? ?? '',
      quantity: (json['quantity'] as num?)?.toDouble() ?? 1.0,
      salePrice: (json['sale_price'] as num?)?.toInt() ?? 0,
      totalPrice: (json['total_price'] as num?)?.toInt() ?? 0,
      isSystemPrice: json['is_system_price'] as bool? ?? true,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'variant_id': variantId,
      'product_name': productName,
      'volume_name': volumeName,
      'quantity': quantity,
      'sale_price': salePrice,
      'total_price': totalPrice,
      'is_system_price': isSystemPrice,
    };
  }
}

class SaleRecord {
  final int id;
  final String operationId;
  final String invoiceNumber;
  final String customerName;
  final int totalAmount;
  final int paidAmount;
  final int debtAmount;
  final int? grossProfit;
  final String paymentType;
  final String paymentMethod;
  final String status;
  final DateTime createdAt;
  final List<SaleItemRecord> items;

  const SaleRecord({
    required this.id,
    this.operationId = '',
    required this.invoiceNumber,
    required this.customerName,
    required this.totalAmount,
    required this.paidAmount,
    required this.debtAmount,
    this.grossProfit,
    required this.paymentType,
    this.paymentMethod = 'CASH',
    this.status = 'COMPLETED',
    required this.createdAt,
    this.items = const [],
  });

  factory SaleRecord.fromJson(Map<String, dynamic> json) {
    return SaleRecord(
      id: (json['id'] ?? json['sale_id']) as int? ?? 0,
      operationId: json['operation_id'] as String? ?? '',
      invoiceNumber: json['invoice_number'] as String? ?? 'INV-000',
      customerName: json['customer_name'] as String? ?? 'Tezkor xaridor',
      totalAmount: (json['total_amount'] as num?)?.toInt() ?? 0,
      paidAmount: (json['paid_amount'] as num?)?.toInt() ?? 0,
      debtAmount: (json['debt_amount'] as num?)?.toInt() ?? 0,
      grossProfit: json['gross_profit'] != null
          ? (json['gross_profit'] as num).toInt()
          : null,
      paymentType: (json['payment_type'] as String? ?? 'CASH').toUpperCase(),
      paymentMethod: (json['payment_method'] as String? ?? 'CASH').toUpperCase(),
      status: json['status'] as String? ?? 'COMPLETED',
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'] as String) ?? DateTime.now()
          : DateTime.now(),
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => SaleItemRecord.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'operation_id': operationId,
      'invoice_number': invoiceNumber,
      'customer_name': customerName,
      'total_amount': totalAmount,
      'paid_amount': paidAmount,
      'debt_amount': debtAmount,
      'gross_profit': grossProfit,
      'payment_type': paymentType,
      'payment_method': paymentMethod,
      'status': status,
      'created_at': createdAt.toIso8601String(),
      'items': items.map((i) => i.toJson()).toList(),
    };
  }
}
