class StockAllocationModel {
  final int allocationId;
  final int variantId;
  final String sku;
  final String productName;
  final String volumeName;
  final int allocatedQuantity;
  final int consumedQuantity;
  final int returnedQuantity;
  final int availableQuantity;

  const StockAllocationModel({
    required this.allocationId,
    required this.variantId,
    this.sku = '',
    this.productName = '',
    this.volumeName = '',
    required this.allocatedQuantity,
    required this.consumedQuantity,
    this.returnedQuantity = 0,
    required this.availableQuantity,
  });

  factory StockAllocationModel.fromJson(Map<String, dynamic> json) {
    return StockAllocationModel(
      allocationId: (json['allocation_id'] as num?)?.toInt() ?? 0,
      variantId: (json['variant_id'] as num?)?.toInt() ?? 0,
      sku: (json['sku'] as String?) ?? '',
      productName: (json['product_name'] as String?) ?? '',
      volumeName: (json['volume_name'] as String?) ?? '',
      allocatedQuantity: (json['allocated_quantity'] as num?)?.toInt() ?? 0,
      consumedQuantity: (json['consumed_quantity'] as num?)?.toInt() ?? 0,
      returnedQuantity: (json['returned_quantity'] as num?)?.toInt() ?? 0,
      availableQuantity: (json['available_quantity'] as num?)?.toInt() ??
          (((json['allocated_quantity'] as num?)?.toInt() ?? 0) -
              ((json['consumed_quantity'] as num?)?.toInt() ?? 0) -
              ((json['returned_quantity'] as num?)?.toInt() ?? 0)),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'allocation_id': allocationId,
      'variant_id': variantId,
      'sku': sku,
      'product_name': productName,
      'volume_name': volumeName,
      'allocated_quantity': allocatedQuantity,
      'consumed_quantity': consumedQuantity,
      'returned_quantity': returnedQuantity,
      'available_quantity': availableQuantity,
    };
  }

  StockAllocationModel copyWith({
    int? allocatedQuantity,
    int? consumedQuantity,
    int? returnedQuantity,
    int? availableQuantity,
  }) {
    return StockAllocationModel(
      allocationId: allocationId,
      variantId: variantId,
      sku: sku,
      productName: productName,
      volumeName: volumeName,
      allocatedQuantity: allocatedQuantity ?? this.allocatedQuantity,
      consumedQuantity: consumedQuantity ?? this.consumedQuantity,
      returnedQuantity: returnedQuantity ?? this.returnedQuantity,
      availableQuantity: availableQuantity ?? this.availableQuantity,
    );
  }
}
