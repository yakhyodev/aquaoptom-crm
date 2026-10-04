class CustomerModel {
  final int id;
  final String uuid;
  final String name;
  final String? phone;
  final String? storeName;
  final String? address;
  final int debtLimit;
  final int currentDebt;
  final String displayName;

  const CustomerModel({
    required this.id,
    this.uuid = '',
    required this.name,
    this.phone,
    this.storeName,
    this.address,
    this.debtLimit = 0,
    this.currentDebt = 0,
    required this.displayName,
  });

  factory CustomerModel.fromJson(Map<String, dynamic> json) {
    return CustomerModel(
      id: json['id'] as int? ?? 0,
      uuid: json['uuid'] as String? ?? '',
      name: json['name'] as String? ?? '',
      phone: json['phone'] as String?,
      storeName: json['store_name'] as String?,
      address: json['address'] as String?,
      debtLimit: (json['debt_limit'] as num?)?.toInt() ?? 0,
      currentDebt: (json['current_debt'] as num?)?.toInt() ?? 0,
      displayName: json['display_name'] as String? ??
          (json['name'] as String? ?? 'Noma\'lum mijoz'),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'uuid': uuid,
      'name': name,
      'phone': phone,
      'store_name': storeName,
      'address': address,
      'debt_limit': debtLimit,
      'current_debt': currentDebt,
      'display_name': displayName,
    };
  }

  /// Qarz holati: musbat bo'lsa qarzdor, manfiy bo'lsa avans egasi
  bool get hasDebt => currentDebt > 0;
  bool get hasAdvance => currentDebt < 0;
}
