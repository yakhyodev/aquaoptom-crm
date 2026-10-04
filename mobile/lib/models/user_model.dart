class UserModel {
  final int id;
  final String name;
  final String email;
  final String role;
  final List<String> permissions;

  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.permissions = const [],
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: (json['role'] as String? ?? 'CASHIER').toUpperCase(),
      permissions: (json['permissions'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'role': role,
      'permissions': permissions,
    };
  }

  bool get isOwner => role.toUpperCase() == 'OWNER';
  bool get isAdmin => role.toUpperCase() == 'ADMIN' || isOwner;
  bool get isSalesManager => role.toUpperCase() == 'SALES_MANAGER' || isAdmin;
  bool get isCashier => role.toUpperCase() == 'CASHIER' || isAdmin;
  bool get isWarehouse => role.toUpperCase() == 'WAREHOUSE' || isAdmin;

  bool hasPermission(String perm) =>
      isAdmin || permissions.contains(perm);

  bool get canViewCost =>
      isOwner || hasPermission('view_cost_price');

  bool get canReceiveStock =>
      isAdmin || isWarehouse || hasPermission('receive_stock');

  bool get canViewDebts =>
      isAdmin || isSalesManager || isCashier || hasPermission('view_debts');
}
