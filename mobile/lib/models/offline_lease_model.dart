class OfflineLeaseModel {
  final String deviceUuid;
  final int userId;
  final String leaseToken;
  final DateTime validFrom;
  final DateTime expiresAt;
  final List<String> permissions;
  final int epoch;
  final String signature;

  const OfflineLeaseModel({
    required this.deviceUuid,
    required this.userId,
    required this.leaseToken,
    required this.validFrom,
    required this.expiresAt,
    required this.permissions,
    required this.epoch,
    required this.signature,
  });

  bool get isExpired => DateTime.now().toUtc().isAfter(expiresAt);

  bool hasPermission(String perm) {
    return permissions.contains(perm) ||
        permissions.contains('admin') ||
        permissions.contains('owner');
  }

  bool get canSell =>
      hasPermission('manage_sales') ||
      hasPermission('create_sale') ||
      hasPermission('sales') ||
      hasPermission('sell_products');

  factory OfflineLeaseModel.fromJson(Map<String, dynamic> json,
      {String? fallbackDeviceUuid, int? fallbackUserId}) {
    return OfflineLeaseModel(
      deviceUuid: (json['device_uuid'] as String?) ?? fallbackDeviceUuid ?? '',
      userId: (json['user_id'] as num?)?.toInt() ?? fallbackUserId ?? 0,
      leaseToken: (json['lease_token'] as String?) ?? '',
      validFrom: json['valid_from'] != null
          ? DateTime.parse(json['valid_from'].toString()).toUtc()
          : DateTime.now().toUtc(),
      expiresAt: json['expires_at'] != null
          ? DateTime.parse(json['expires_at'].toString()).toUtc()
          : DateTime.now().toUtc().add(const Duration(hours: 24)),
      permissions: (json['permissions'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      epoch: (json['epoch'] as num?)?.toInt() ?? 1,
      signature: (json['signature'] as String?) ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'device_uuid': deviceUuid,
      'user_id': userId,
      'lease_token': leaseToken,
      'valid_from': validFrom.toIso8601String(),
      'expires_at': expiresAt.toIso8601String(),
      'permissions': permissions,
      'epoch': epoch,
      'signature': signature,
    };
  }
}
