class SupplierModel {
  final int id;
  final String uuid;
  final String name;
  final String? companyName;
  final String? phone;
  final String? address;
  final int balance;
  final int creditLimit;
  final String displayName;

  const SupplierModel({
    required this.id,
    this.uuid = '',
    required this.name,
    this.companyName,
    this.phone,
    this.address,
    this.balance = 0,
    this.creditLimit = 0,
    required this.displayName,
  });

  factory SupplierModel.fromJson(Map<String, dynamic> json) {
    return SupplierModel(
      id: json['id'] as int? ?? 0,
      uuid: json['uuid'] as String? ?? '',
      name: json['name'] as String? ?? '',
      companyName: json['company_name'] as String?,
      phone: json['phone'] as String?,
      address: json['address'] as String?,
      balance: (json['balance'] as num?)?.toInt() ?? 0,
      creditLimit: (json['credit_limit'] as num?)?.toInt() ?? 0,
      displayName: json['display_name'] as String? ??
          (json['name'] as String? ?? 'Noma\'lum ta\'minotchi'),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'uuid': uuid,
      'name': name,
      'company_name': companyName,
      'phone': phone,
      'address': address,
      'balance': balance,
      'credit_limit': creditLimit,
      'display_name': displayName,
    };
  }

  /// Balans: musbat bo'lsa biz ulardan qarzdormiz, manfiy bo'lsa biz ortiqcha avans to'laganmiz
  bool get hasDebtToSupplier => balance > 0;
  bool get hasAdvanceToSupplier => balance < 0;
}
