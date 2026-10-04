class CashAccountModel {
  final int id;
  final String name;
  final String type;
  final int balance;
  final bool isDefault;

  const CashAccountModel({
    required this.id,
    required this.name,
    required this.type,
    required this.balance,
    this.isDefault = false,
  });

  factory CashAccountModel.fromJson(Map<String, dynamic> json) {
    return CashAccountModel(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? 'Kassa',
      type: json['type'] as String? ?? 'CASH',
      balance: (json['balance'] as num?)?.toInt() ?? 0,
      isDefault: json['is_default'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'type': type,
      'balance': balance,
      'is_default': isDefault,
    };
  }
}
