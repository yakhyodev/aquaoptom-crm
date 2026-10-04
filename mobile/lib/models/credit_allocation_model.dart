class CreditAllocationModel {
  final int allocationId;
  final int customerId;
  final String customerName;
  final int allocatedAmount;
  final int consumedAmount;
  final int returnedAmount;
  final int availableAmount;

  const CreditAllocationModel({
    required this.allocationId,
    required this.customerId,
    this.customerName = '',
    required this.allocatedAmount,
    required this.consumedAmount,
    this.returnedAmount = 0,
    required this.availableAmount,
  });

  factory CreditAllocationModel.fromJson(Map<String, dynamic> json) {
    return CreditAllocationModel(
      allocationId: (json['allocation_id'] as num?)?.toInt() ?? 0,
      customerId: (json['customer_id'] as num?)?.toInt() ?? 0,
      customerName: (json['customer_name'] as String?) ?? '',
      allocatedAmount: (json['allocated_amount'] as num?)?.toInt() ?? 0,
      consumedAmount: (json['consumed_amount'] as num?)?.toInt() ?? 0,
      returnedAmount: (json['returned_amount'] as num?)?.toInt() ?? 0,
      availableAmount: (json['available_amount'] as num?)?.toInt() ??
          (((json['allocated_amount'] as num?)?.toInt() ?? 0) -
              ((json['consumed_amount'] as num?)?.toInt() ?? 0) -
              ((json['returned_amount'] as num?)?.toInt() ?? 0)),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'allocation_id': allocationId,
      'customer_id': customerId,
      'customer_name': customerName,
      'allocated_amount': allocatedAmount,
      'consumed_amount': consumedAmount,
      'returned_amount': returnedAmount,
      'available_amount': availableAmount,
    };
  }

  CreditAllocationModel copyWith({
    int? allocatedAmount,
    int? consumedAmount,
    int? returnedAmount,
    int? availableAmount,
  }) {
    return CreditAllocationModel(
      allocationId: allocationId,
      customerId: customerId,
      customerName: customerName,
      allocatedAmount: allocatedAmount ?? this.allocatedAmount,
      consumedAmount: consumedAmount ?? this.consumedAmount,
      returnedAmount: returnedAmount ?? this.returnedAmount,
      availableAmount: availableAmount ?? this.availableAmount,
    );
  }
}
