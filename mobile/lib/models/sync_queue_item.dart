import 'dart:convert';

class SyncQueueItem {
  final String operationId;
  final int userId;
  final String deviceUuid;
  final String type;
  final Map<String, dynamic> payload;
  final String payloadFingerprint;
  final String status; // PENDING, PROCESSING, ACKNOWLEDGED, NEEDS_REVIEW, CONFLICT, FAILED
  final int retryCount;
  final String? errorCode;
  final String? errorMessage;
  final int? serverDocumentId;
  final String? serverDocumentNumber;
  final Map<String, dynamic>? ackPayload;
  final DateTime deviceCreatedAt;
  final DateTime? processedAt;
  final String? leaseToken;
  final DateTime? lockedUntil;
  final String? workerId;

  const SyncQueueItem({
    required this.operationId,
    required this.userId,
    required this.deviceUuid,
    required this.type,
    required this.payload,
    required this.payloadFingerprint,
    this.status = 'PENDING',
    this.retryCount = 0,
    this.errorCode,
    this.errorMessage,
    this.serverDocumentId,
    this.serverDocumentNumber,
    this.ackPayload,
    required this.deviceCreatedAt,
    this.processedAt,
    this.leaseToken,
    this.lockedUntil,
    this.workerId,
  });

  bool get isPending => status == 'PENDING';
  bool get isAcknowledged => status == 'ACKNOWLEDGED';
  bool get isNeedsReview => status == 'NEEDS_REVIEW';
  bool get isConflict => status == 'CONFLICT';
  bool get isFailed => status == 'FAILED';

  bool isLocked(DateTime now) {
    if (lockedUntil == null) return false;
    return lockedUntil!.isAfter(now);
  }

  factory SyncQueueItem.fromDbMap(Map<String, dynamic> map) {
    Map<String, dynamic> parsedPayload = {};
    try {
      final raw = map['payload'];
      if (raw is String) {
        parsedPayload = json.decode(raw) as Map<String, dynamic>;
      } else if (raw is Map) {
        parsedPayload = Map<String, dynamic>.from(raw);
      }
    } catch (_) {}

    Map<String, dynamic>? parsedAck;
    if (map['ack_payload'] != null) {
      try {
        final raw = map['ack_payload'];
        if (raw is String) {
          parsedAck = json.decode(raw) as Map<String, dynamic>;
        } else if (raw is Map) {
          parsedAck = Map<String, dynamic>.from(raw);
        }
      } catch (_) {}
    }

    return SyncQueueItem(
      operationId: map['operation_id'] as String,
      userId: (map['user_id'] as num?)?.toInt() ?? 0,
      deviceUuid: (map['device_uuid'] as String?) ?? '',
      type: (map['type'] as String?) ?? 'CREATE_SALE',
      payload: parsedPayload,
      payloadFingerprint: (map['payload_fingerprint'] as String?) ?? '',
      status: (map['status'] as String?) ?? 'PENDING',
      retryCount: (map['retry_count'] as num?)?.toInt() ?? 0,
      errorCode: map['error_code'] as String?,
      errorMessage: map['error_message'] as String?,
      serverDocumentId: (map['server_document_id'] as num?)?.toInt(),
      serverDocumentNumber: map['server_document_number'] as String?,
      ackPayload: parsedAck,
      deviceCreatedAt: map['device_created_at'] != null
          ? DateTime.parse(map['device_created_at'].toString()).toUtc()
          : DateTime.now().toUtc(),
      processedAt: map['processed_at'] != null
          ? DateTime.parse(map['processed_at'].toString()).toUtc()
          : null,
      leaseToken: map['lease_token'] as String?,
      lockedUntil: map['locked_until'] != null
          ? DateTime.parse(map['locked_until'].toString()).toUtc()
          : null,
      workerId: map['worker_id'] as String?,
    );
  }

  Map<String, dynamic> toDbMap() {
    return {
      'operation_id': operationId,
      'user_id': userId,
      'device_uuid': deviceUuid,
      'type': type,
      'payload': json.encode(payload),
      'payload_fingerprint': payloadFingerprint,
      'status': status,
      'retry_count': retryCount,
      'error_code': errorCode,
      'error_message': errorMessage,
      'server_document_id': serverDocumentId,
      'server_document_number': serverDocumentNumber,
      'ack_payload': ackPayload != null ? json.encode(ackPayload) : null,
      'device_created_at': deviceCreatedAt.toIso8601String(),
      'processed_at': processedAt?.toIso8601String(),
      'lease_token': leaseToken,
      'locked_until': lockedUntil?.toIso8601String(),
      'worker_id': workerId,
    };
  }

  /// Push API so'rovi uchun format
  Map<String, dynamic> toPushOperation() {
    return {
      'operation_id': operationId,
      'type': type,
      'device_created_at': deviceCreatedAt.toIso8601String(),
      'payload': payload,
    };
  }

  SyncQueueItem copyWith({
    String? status,
    int? retryCount,
    String? errorCode,
    String? errorMessage,
    int? serverDocumentId,
    String? serverDocumentNumber,
    Map<String, dynamic>? ackPayload,
    DateTime? processedAt,
    DateTime? lockedUntil,
    String? workerId,
  }) {
    return SyncQueueItem(
      operationId: operationId,
      userId: userId,
      deviceUuid: deviceUuid,
      type: type,
      payload: payload,
      payloadFingerprint: payloadFingerprint,
      status: status ?? this.status,
      retryCount: retryCount ?? this.retryCount,
      errorCode: errorCode ?? this.errorCode,
      errorMessage: errorMessage ?? this.errorMessage,
      serverDocumentId: serverDocumentId ?? this.serverDocumentId,
      serverDocumentNumber: serverDocumentNumber ?? this.serverDocumentNumber,
      ackPayload: ackPayload ?? this.ackPayload,
      deviceCreatedAt: deviceCreatedAt,
      processedAt: processedAt ?? this.processedAt,
      leaseToken: leaseToken,
      lockedUntil: lockedUntil ?? this.lockedUntil,
      workerId: workerId ?? this.workerId,
    );
  }
}
