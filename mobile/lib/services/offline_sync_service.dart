import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:sqflite/sqflite.dart';
import '../config/app_config.dart';
import '../database/app_database.dart';
import '../models/sync_queue_item.dart';
import 'api_exceptions.dart';
import 'session_service.dart';

class SyncStatusSummary {
  final int pendingCount;
  final int acknowledgedCount;
  final int needsReviewCount;
  final int conflictCount;
  final bool isSyncing;
  final String? lastError;
  final DateTime? lastSyncedAt;

  const SyncStatusSummary({
    this.pendingCount = 0,
    this.acknowledgedCount = 0,
    this.needsReviewCount = 0,
    this.conflictCount = 0,
    this.isSyncing = false,
    this.lastError,
    this.lastSyncedAt,
  });
}

class OfflineSyncService extends ChangeNotifier {
  static final OfflineSyncService _instance = OfflineSyncService._internal();
  factory OfflineSyncService({AppDatabase? appDb, http.Client? client}) {
    if (appDb != null) _instance._appDb = appDb;
    if (client != null) _instance._client = client;
    return _instance;
  }
  OfflineSyncService._internal()
    : _appDb = AppDatabase(),
      _client = http.Client();

  AppDatabase _appDb;
  http.Client _client;

  String? _deviceUuid;
  String get deviceUuid => _deviceUuid ?? '';

  void setDeviceUuid(String uuid) {
    _deviceUuid = uuid;
  }

  bool _isSyncing = false;
  bool get isSyncing => _isSyncing;

  String? _lastError;
  String? get lastError => _lastError;

  DateTime? _lastSyncedAt;
  DateTime? get lastSyncedAt => _lastSyncedAt;

  Map<String, String> _buildHeaders() {
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Device-UUID': deviceUuid,
    };
    final token = SessionService().token;
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  /// Server aloqasini tekshirish (Health Check)
  Future<bool> checkHealth() async {
    try {
      final res = await _client
          .get(
            Uri.parse('${AppConfig.apiBaseUrl}/health'),
            headers: {'Accept': 'application/json'},
          )
          .timeout(const Duration(seconds: 5));
      return res.statusCode == 200;
    } catch (_) {
      return false;
    }
  }

  /// Qurilmani ishga tushirish (Bootstrap): lease, snapshotlar va kursorni yuklash
  Future<Map<String, dynamic>> bootstrap({String? leaseToken}) async {
    final user = SessionService().currentUser;
    if (user == null) {
      throw const UnauthorizedException("Tizimga kirilmagan.");
    }

    final headers = _buildHeaders();
    if (leaseToken != null) {
      headers['X-Lease-Token'] = leaseToken;
    }

    final response = await _client.post(
      Uri.parse('${AppConfig.apiBaseUrl}/sync/bootstrap'),
      headers: headers,
      body: json.encode({if (deviceUuid.isNotEmpty) 'device_uuid': deviceUuid}),
    );

    if (response.statusCode != 200) {
      throw ServerException(
        "Bootstrap muvaffaqiyatsiz tugadi (${response.statusCode})",
      );
    }

    final data =
        (json.decode(response.body)['data'] as Map<String, dynamic>?) ?? {};
    final db = await _appDb.database;

    await db.transaction((txn) async {
      final unresolved = await txn.query(
        'sync_queue',
        columns: ['operation_id'],
        where: "status != 'ACKNOWLEDGED'",
        limit: 1,
      );
      if (unresolved.isNotEmpty) {
        throw const ServerException(
          'Avval saqlangan offline amallarni sinxronlang. Ajratmalar qayta yuklanmadi.',
        );
      }
      final assignedDeviceUuid =
          (data['device'] as Map<String, dynamic>?)?['device_uuid'] as String?;
      if (assignedDeviceUuid == null || assignedDeviceUuid.isEmpty) {
        throw const ServerException(
          'Server qurilma identifikatorini qaytarmadi.',
        );
      }
      // 1. Lease saqlash
      final leaseData = data['lease'] as Map<String, dynamic>?;
      if (leaseData != null) {
        await txn.delete(
          'offline_leases',
          where: 'user_id = ?',
          whereArgs: [user.id],
        );
        await txn.insert('offline_leases', {
          'device_uuid': assignedDeviceUuid,
          'user_id': user.id,
          'lease_token': leaseData['lease_token'] ?? '',
          'valid_from':
              leaseData['valid_from'] ?? DateTime.now().toIso8601String(),
          'expires_at':
              leaseData['expires_at'] ??
              DateTime.now().add(const Duration(hours: 24)).toIso8601String(),
          'permissions': json.encode(leaseData['permissions'] ?? []),
          'epoch': leaseData['epoch'] ?? 1,
          'signature': leaseData['signature'] ?? '',
          'is_active': 1,
        });
      }

      // 2. Tovar ajratmalari (Stock Allocations)
      final stockAllocations =
          (data['stock_allocations'] as List<dynamic>?) ?? [];
      await txn.delete('stock_allocations');
      for (final alloc in stockAllocations) {
        final a = alloc as Map<String, dynamic>;
        await txn.insert('stock_allocations', {
          'id': a['allocation_id'] ?? a['id'],
          'variant_id': a['variant_id'],
          'sku': a['sku'] ?? '',
          'product_name': a['product_name'] ?? '',
          'volume_name': a['volume_name'] ?? '',
          'allocated_quantity': a['allocated_quantity'] ?? 0,
          'consumed_quantity': a['consumed_quantity'] ?? 0,
          'returned_quantity': a['returned_quantity'] ?? 0,
          'available_quantity': a['available_quantity'] ?? 0,
          'updated_at': DateTime.now().toIso8601String(),
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }

      // 3. Kredit ajratmalari (Credit Allocations)
      final creditAllocations =
          (data['credit_allocations'] as List<dynamic>?) ?? [];
      await txn.delete('credit_allocations');
      for (final alloc in creditAllocations) {
        final a = alloc as Map<String, dynamic>;
        await txn.insert('credit_allocations', {
          'id': a['allocation_id'] ?? a['id'],
          'customer_id': a['customer_id'],
          'customer_name': a['customer_name'] ?? '',
          'allocated_amount': a['allocated_amount'] ?? 0,
          'consumed_amount': a['consumed_amount'] ?? 0,
          'returned_amount': a['returned_amount'] ?? 0,
          'available_amount': a['available_amount'] ?? 0,
          'updated_at': DateTime.now().toIso8601String(),
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }

      // 4. Mahsulotlar katalogi
      final catalog = (data['catalog'] as List<dynamic>?) ?? [];
      final groupedProducts = <int, Map<String, dynamic>>{};
      for (final row in catalog) {
        final variant = row as Map<String, dynamic>;
        final productId = (variant['product_id'] as num).toInt();
        final product = groupedProducts.putIfAbsent(
          productId,
          () => {
            'id': productId,
            'name': variant['product_name'],
            'is_active': true,
            'variants': <Map<String, dynamic>>[],
          },
        );
        (product['variants'] as List<Map<String, dynamic>>).add({
          ...variant,
          'litres': double.tryParse('${variant['volume_litres']}') ?? 0,
          'volume_ml': variant['volume_ml'],
          'display_volume': variant['volume_name'],
        });
      }
      final products =
          data['products'] as List<dynamic>? ?? groupedProducts.values.toList();
      for (final prod in products) {
        final p = prod as Map<String, dynamic>;
        await txn.insert('products', {
          'id': p['id'],
          'name': p['name'],
          'code': p['code'] ?? '',
          'is_active': (p['is_active'] == true || p['is_active'] == 1) ? 1 : 0,
          'updated_at': DateTime.now().toIso8601String(),
        }, conflictAlgorithm: ConflictAlgorithm.replace);

        final variants = (p['variants'] as List<dynamic>?) ?? [];
        for (final v in variants) {
          final vr = v as Map<String, dynamic>;
          await txn.insert('product_variants', {
            'id': vr['id'],
            'product_id': p['id'],
            'sku': vr['sku'] ?? '',
            'litres': (vr['litres'] as num?)?.toDouble() ?? 0.5,
            'volume_ml': (vr['volume_ml'] as num?)?.toInt() ?? 500,
            'display_volume': vr['display_volume'] ?? '',
            'stock_qty': (vr['stock_qty'] as num?)?.toInt() ?? 0,
            'cost_price': (vr['cost_price'] as num?)?.toInt(),
            'retail_price': (vr['retail_price'] as num?)?.toInt() ?? 0,
            'default_sale_price':
                (vr['default_sale_price'] as num?)?.toInt() ?? 0,
            'updated_at': DateTime.now().toIso8601String(),
          }, conflictAlgorithm: ConflictAlgorithm.replace);
        }
      }

      // 5. Mijozlar
      final customers = (data['customers'] as List<dynamic>?) ?? [];
      for (final cust in customers) {
        final c = cust as Map<String, dynamic>;
        await txn.insert('customers', {
          'id': c['id'],
          'uuid': c['uuid'],
          'name': c['name'],
          'phone': c['phone'],
          'store_name': c['store_name'],
          'address': c['address'],
          'current_debt': (c['current_debt'] as num?)?.toInt() ?? 0,
          'debt_limit': (c['debt_limit'] as num?)?.toInt() ?? 0,
          'is_active':
              (c['is_active'] == null ||
                  c['is_active'] == true ||
                  c['is_active'] == 1)
              ? 1
              : 0,
          'created_offline': 0,
          'updated_at': DateTime.now().toIso8601String(),
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }

      // 6. Kassa hisoblari
      final cashAccounts = (data['cash_accounts'] as List<dynamic>?) ?? [];
      for (final acc in cashAccounts) {
        final a = acc as Map<String, dynamic>;
        await txn.insert('cash_accounts', {
          'id': a['id'],
          'name': a['name'],
          'type': a['type'] ?? 'CASH',
          'balance': (a['balance'] as num?)?.toInt() ?? 0,
          'is_default': (a['is_default'] == true || a['is_default'] == 1)
              ? 1
              : 0,
          'is_active': (a['is_active'] == true || a['is_active'] == 1) ? 1 : 0,
          'updated_at': DateTime.now().toIso8601String(),
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }

      // 7. Kursor
      final cursorVal = (data['current_cursor'] as num?)?.toInt() ?? 0;
      await txn.insert('sync_cursor', {
        'id': 1,
        'cursor_pos': cursorVal,
        'last_synced_at': DateTime.now().toIso8601String(),
      }, conflictAlgorithm: ConflictAlgorithm.replace);
    });

    setDeviceUuid(
      (data['device'] as Map<String, dynamic>)['device_uuid'] as String,
    );
    _lastSyncedAt = DateTime.now();
    notifyListeners();
    return data;
  }

  Future<int> reconcileRecovery(int serverEpoch) async {
    final user = SessionService().currentUser;
    if (user == null) {
      throw const UnauthorizedException('Login talab qilinadi.');
    }
    final db = await _appDb.database;
    await db.insert('app_meta', {
      'key': 'recovery_hold',
      'value': 'true',
    }, conflictAlgorithm: ConflictAlgorithm.replace);
    final rows = await db.query(
      'sync_queue',
      where: 'user_id = ?',
      whereArgs: [user.id],
      orderBy: 'device_created_at ASC',
    );
    final retained = rows.map(SyncQueueItem.fromDbMap).toList();
    final uuid = retained.isNotEmpty
        ? retained.first.deviceUuid
        : (_deviceUuid ?? '');
    if (retained.any((item) => item.deviceUuid != uuid)) {
      throw const ServerException(
        'Turli qurilma yozuvlari ajratib tekshirilishi kerak.',
      );
    }
    if (uuid.isEmpty) {
      throw const ServerException(
        'Recovery uchun qurilma identifikatori kerak.',
      );
    }
    final epochs = await db.query(
      'app_meta',
      where: 'key = ?',
      whereArgs: ['recovery_epoch'],
    );
    final epoch = epochs.isEmpty
        ? 1
        : int.tryParse(epochs.first['value'] as String) ?? 1;
    var count = 0;
    for (var offset = 0; offset < max(1, retained.length); offset += 100) {
      final batch = retained
          .skip(offset)
          .take(100)
          .map((item) => item.toPushOperation())
          .toList();
      final response = await _client
          .post(
            Uri.parse('${AppConfig.apiBaseUrl}/sync/reconcile-recovery'),
            headers: {..._buildHeaders(), 'X-Device-UUID': uuid},
            body: json.encode({
              'device_uuid': uuid,
              'client_epoch': epoch,
              'operations': batch,
            }),
          )
          .timeout(const Duration(seconds: 20));
      if (response.statusCode != 200) {
        throw const ServerException(
          'Recovery bajarilmadi; barcha lokal yozuvlar saqlandi.',
        );
      }
      final data = json.decode(response.body) as Map<String, dynamic>;
      final results = (data['results'] as List<dynamic>?) ?? [];
      await db.transaction((txn) async {
        for (final raw in results) {
          final result = raw as Map<String, dynamic>;
          final status = result['status'];
          final acknowledged = [
            'ALREADY_PERSISTED',
            'RESTORED_AND_APPLIED',
            'APPLIED',
            'RETRY_SUCCESS',
          ].contains(status);
          await txn.update(
            'sync_queue',
            {
              'status': acknowledged
                  ? 'ACKNOWLEDGED'
                  : status == 'CONFLICT_MISMATCH'
                  ? 'CONFLICT'
                  : 'NEEDS_REVIEW',
              'ack_payload': acknowledged ? json.encode(result) : null,
              'server_document_id': result['server_document_id'],
              'server_document_number': result['server_document_number'],
              'error_code': acknowledged
                  ? null
                  : result['error_code'] ?? 'RECOVERY_REVIEW_REQUIRED',
              'error_message': result['message'],
              'worker_id': null,
              'locked_until': null,
            },
            where: 'operation_id = ? AND user_id = ?',
            whereArgs: [result['operation_id'], user.id],
          );
          count++;
        }
      });
    }
    await db.insert('app_meta', {
      'key': 'recovery_epoch',
      'value': serverEpoch.toString(),
    }, conflictAlgorithm: ConflictAlgorithm.replace);
    await db.insert('sync_cursor', {
      'id': 1,
      'cursor_pos': 0,
    }, conflictAlgorithm: ConflictAlgorithm.replace);
    return count;
  }

  Future<bool> _checkRecoveryState() async {
    final response = await _client
        .get(
          Uri.parse('${AppConfig.apiBaseUrl}/sync/health'),
          headers: _buildHeaders(),
        )
        .timeout(const Duration(seconds: 5));
    if (response.statusCode != 200) {
      throw const ServerException('Sync holatini tekshirish bajarilmadi.');
    }
    final state = json.decode(response.body) as Map<String, dynamic>;
    if (state['recovery_status'] == 'RECONCILIATION_REQUIRED') {
      await reconcileRecovery((state['recovery_epoch'] as num?)?.toInt() ?? 1);
      return true;
    }
    final db = await _appDb.database;
    final hold = await db.query(
      'app_meta',
      where: 'key = ?',
      whereArgs: ['recovery_hold'],
    );
    if (hold.isNotEmpty && hold.first['value'] == 'true') {
      await bootstrap();
      await db.insert('app_meta', {
        'key': 'recovery_hold',
        'value': 'false',
      }, conflictAlgorithm: ConflictAlgorithm.replace);
    }
    return false;
  }

  /// Kutilayotgan operatsiyalarni serverga yuborish (Push Pending Operations)
  Future<int> pushPendingOperations({
    String? specificWorkerId,
    String? deviceUuid,
  }) async {
    final user = SessionService().currentUser;
    if (user == null) return 0;

    final db = await _appDb.database;
    final workerId = specificWorkerId ?? 'worker-${Random().nextInt(100000)}';
    final lockExpiry = DateTime.now().toUtc().add(const Duration(seconds: 30));

    // 1. Atomik Lock / Lease olish (Worker lease: process kill/restartdan keyin vaqt tugasa bo'shaydi)
    List<SyncQueueItem> lockedItems = [];

    await db.transaction((txn) async {
      // Eski o'tib ketgan locklarni tozalash
      final nowIso = DateTime.now().toUtc().toIso8601String();
      await txn.rawUpdate(
        '''
        UPDATE sync_queue 
        SET locked_until = NULL, worker_id = NULL 
        WHERE status = 'PENDING' AND locked_until IS NOT NULL AND locked_until < ?
      ''',
        [nowIso],
      );

      // Faqat ayni user_id ga tegishli PENDING yozuvlarni qulflash (User Isolation!)
      await txn.rawUpdate(
        '''
        UPDATE sync_queue 
        SET locked_until = ?, worker_id = ? 
        WHERE user_id = ? AND status = 'PENDING' AND (locked_until IS NULL OR locked_until < ?)
      ''',
        [lockExpiry.toIso8601String(), workerId, user.id, nowIso],
      );

      final rows = await txn.query(
        'sync_queue',
        where: 'user_id = ? AND worker_id = ? AND status = ?',
        whereArgs: [user.id, workerId, 'PENDING'],
        orderBy:
            "CASE type WHEN 'CREATE_CUSTOMER' THEN 1 WHEN 'CREATE_SALE' THEN 2 ELSE 3 END, device_created_at ASC",
      );

      lockedItems = rows.map((r) => SyncQueueItem.fromDbMap(r)).toList();
    });

    if (lockedItems.isEmpty) {
      return 0;
    }

    final effectiveDeviceUuid = deviceUuid ?? lockedItems.first.deviceUuid;

    // 2. Batch payload tayyorlash
    final pushOperations = lockedItems
        .map((item) => item.toPushOperation())
        .toList();
    final firstLeaseToken = lockedItems.first.leaseToken;

    final headers = {..._buildHeaders(), 'X-Device-UUID': effectiveDeviceUuid};
    if (firstLeaseToken != null) {
      headers['X-Lease-Token'] = firstLeaseToken;
    }

    try {
      final response = await _client
          .post(
            Uri.parse('${AppConfig.apiBaseUrl}/sync/push'),
            headers: headers,
            body: json.encode({
              'device_uuid': effectiveDeviceUuid,
              'operations': pushOperations,
              'lease_token': firstLeaseToken,
            }),
          )
          .timeout(const Duration(seconds: 20));

      if (response.statusCode == 428) {
        await _releaseLocks(workerId);
        final hold = json.decode(response.body) as Map<String, dynamic>;
        return await reconcileRecovery(
          (hold['recovery_epoch'] as num?)?.toInt() ?? 1,
        );
      }
      if (response.statusCode >= 200 && response.statusCode < 300) {
        final resData = json.decode(response.body) as Map<String, dynamic>;
        final results = (resData['results'] as List<dynamic>?) ?? [];

        await db.transaction((txn) async {
          for (final rawRes in results) {
            final res = rawRes as Map<String, dynamic>;
            final opId = res['operation_id'] as String?;
            if (opId == null) continue;

            final status =
                (res['status'] as String?)?.toUpperCase() ?? 'PENDING';
            final serverDocId = (res['server_document_id'] as num?)?.toInt();
            final serverDocNum = res['server_document_number'] as String?;
            final errorCode = res['error_code'] as String?;
            final errorMsg = res['message'] as String?;

            if (status == 'SUCCESS' ||
                status == 'APPLIED' ||
                status == 'RETRY_SUCCESS') {
              // ACKNOWLEDGED: Original operation_id va ACK payloadni xavfsiz retention uchun saqlaymiz
              await txn.update(
                'sync_queue',
                {
                  'status': 'ACKNOWLEDGED',
                  'server_document_id': serverDocId,
                  'server_document_number': serverDocNum,
                  'ack_payload': json.encode(res),
                  'processed_at': DateTime.now().toUtc().toIso8601String(),
                  'locked_until': null,
                  'worker_id': null,
                },
                where: 'operation_id = ?',
                whereArgs: [opId],
              );
            } else if (status == 'NEEDS_REVIEW') {
              // NEEDS_REVIEW: Navbatdan o'chirilmaydi, sababli resolution uchun belgilanadi
              await txn.update(
                'sync_queue',
                {
                  'status': 'NEEDS_REVIEW',
                  'error_code': errorCode ?? 'NEEDS_REVIEW',
                  'error_message': errorMsg,
                  'locked_until': null,
                  'worker_id': null,
                },
                where: 'operation_id = ?',
                whereArgs: [opId],
              );
            } else if (status == 'CONFLICT') {
              await txn.update(
                'sync_queue',
                {
                  'status': 'CONFLICT',
                  'error_code': errorCode ?? 'PAYLOAD_MISMATCH',
                  'error_message': errorMsg,
                  'locked_until': null,
                  'worker_id': null,
                },
                where: 'operation_id = ?',
                whereArgs: [opId],
              );
            } else {
              // FAILED: Retry sonini oshirish va lockni bo'shatish
              await txn.rawUpdate(
                '''
                UPDATE sync_queue 
                SET retry_count = retry_count + 1, error_code = ?, error_message = ?, locked_until = NULL, worker_id = NULL 
                WHERE operation_id = ?
              ''',
                [errorCode, errorMsg, opId],
              );
            }
          }
        });

        _lastSyncedAt = DateTime.now();
        notifyListeners();
        return results.length;
      } else {
        // HTTP Server xatosi (500, 502): locklarni bo'shatish
        await _releaseLocks(workerId);
        throw ServerException("Server xatosi: ${response.statusCode}");
      }
    } catch (e) {
      // Tarmoq uzilishi yoki timeout: locklarni bo'shatish (original operation_id saqlanadi)
      await _releaseLocks(workerId);
      rethrow;
    }
  }

  Future<void> _releaseLocks(String workerId) async {
    final db = await _appDb.database;
    await db.rawUpdate(
      '''
      UPDATE sync_queue 
      SET locked_until = NULL, worker_id = NULL 
      WHERE worker_id = ?
    ''',
      [workerId],
    );
  }

  /// Kursor bo'yicha serverdagi o'zgarishlarni tortish (Delta Pull)
  Future<int> pullDeltaChanges() async {
    final user = SessionService().currentUser;
    if (user == null) return 0;

    final db = await _appDb.database;

    final cursorRows = await db.query('sync_cursor', where: 'id = 1', limit: 1);
    final curPos = cursorRows.isNotEmpty
        ? ((cursorRows.first['cursor_pos'] as num?)?.toInt() ?? 0)
        : 0;

    final uri = Uri.parse(
      '${AppConfig.apiBaseUrl}/sync/pull',
    ).replace(queryParameters: {'cursor': curPos.toString(), 'limit': '50'});

    final response = await _client.get(uri, headers: _buildHeaders());
    if (response.statusCode != 200) {
      return 0;
    }

    final data =
        (json.decode(response.body)['data'] as Map<String, dynamic>?) ?? {};
    final newCursor = (data['next_cursor'] as num?)?.toInt() ?? curPos;
    final changes = (data['items'] as List<dynamic>?) ?? [];

    if (changes.isNotEmpty) {
      await db.transaction((txn) async {
        for (final item in changes) {
          final ch = item as Map<String, dynamic>;
          final entity = (ch['entity_type'] as String?)?.toUpperCase();
          final payload = ch['payload'] as Map<String, dynamic>? ?? {};

          final id = int.tryParse('${ch['entity_id'] ?? payload['id']}');
          if (id == null) continue;
          if (entity == 'PRODUCT') {
            if (ch['is_tombstone'] == true) {
              await txn.update(
                'products',
                {'is_active': 0},
                where: 'id = ?',
                whereArgs: [id],
              );
            } else if (payload['name'] != null) {
              await txn.insert('products', {
                'id': id,
                'name': payload['name'],
                'code': payload['code'],
                'is_active': payload['status'] == 'ARCHIVED' ? 0 : 1,
              }, conflictAlgorithm: ConflictAlgorithm.replace);
            }
          } else if (entity == 'PRODUCT_VARIANT') {
            if (ch['is_tombstone'] == true) {
              await txn.delete(
                'product_variants',
                where: 'id = ?',
                whereArgs: [id],
              );
              continue;
            }
            final update = <String, Object?>{
              'updated_at': DateTime.now().toIso8601String(),
            };
            for (final field in [
              'stock_qty',
              'retail_price',
              'default_sale_price',
              'sku',
              'product_id',
            ]) {
              if (payload.containsKey(field)) update[field] = payload[field];
            }
            final existing = await txn.query(
              'product_variants',
              where: 'id = ?',
              whereArgs: [id],
            );
            if (existing.isEmpty && payload['product_id'] != null) {
              await txn.insert('product_variants', {'id': id, ...update});
            } else {
              await txn.update(
                'product_variants',
                update,
                where: 'id = ?',
                whereArgs: [id],
              );
            }
          } else if (entity == 'CUSTOMER') {
            final update = <String, Object?>{
              'updated_at': DateTime.now().toIso8601String(),
            };
            for (final field in [
              'name',
              'phone',
              'store_name',
              'address',
              'debt_limit',
              'current_debt',
            ]) {
              if (payload.containsKey(field)) update[field] = payload[field];
            }
            if (payload.containsKey('current_debt')) {
              var pendingDebt = 0;
              final pending = await txn.query(
                'sync_queue',
                where: "user_id = ? AND status != 'ACKNOWLEDGED'",
                whereArgs: [user.id],
              );
              for (final row in pending) {
                final operation = SyncQueueItem.fromDbMap(row);
                if (operation.payload['customer_id'] != id) continue;
                if (operation.type == 'CREATE_SALE') {
                  final items =
                      (operation.payload['items'] as List<dynamic>?) ?? [];
                  final total = items.fold<int>(
                    0,
                    (sum, item) =>
                        sum +
                        ((item['quantity'] as num?)?.toInt() ?? 0) *
                            ((item['sale_price'] as num?)?.toInt() ?? 0),
                  );
                  pendingDebt +=
                      total -
                      ((operation.payload['paid_amount'] as num?)?.toInt() ??
                          0);
                } else if (operation.type == 'CUSTOMER_PAYMENT') {
                  pendingDebt -=
                      (operation.payload['amount'] as num?)?.toInt() ?? 0;
                }
              }
              update['current_debt'] =
                  ((payload['current_debt'] as num?)?.toInt() ?? 0) +
                  pendingDebt;
            }
            final existing = await txn.query(
              'customers',
              where: 'id = ?',
              whereArgs: [id],
            );
            if (existing.isEmpty && payload['name'] != null) {
              await txn.insert('customers', {'id': id, ...update});
            } else {
              await txn.update(
                'customers',
                update,
                where: 'id = ?',
                whereArgs: [id],
              );
            }
          }
        }

        await txn.insert('sync_cursor', {
          'id': 1,
          'cursor_pos': newCursor,
          'last_synced_at': DateTime.now().toIso8601String(),
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      });
    }

    _lastSyncedAt = DateTime.now();
    notifyListeners();
    if (data['has_more'] == true && newCursor > curPos) {
      return changes.length + await pullDeltaChanges();
    }
    return changes.length;
  }

  /// Qo'lda yoki avtomatik to'liq sinxronlash (Sync Now)
  Future<bool> renewDeviceLease({bool force = false}) async {
    final user = SessionService().user;
    if (user == null || deviceUuid.isEmpty) return false;
    final db = await _appDb.database;
    final rows = await db.query('offline_leases', where: 'user_id = ? AND device_uuid = ? AND is_active = 1', whereArgs: [user.id, deviceUuid], limit: 1);
    if (rows.isEmpty) return false;
    final expiry = DateTime.tryParse(rows.first['expires_at'] as String);
    if (!force && expiry != null && expiry.isAfter(DateTime.now().add(const Duration(hours: 4)))) return false;
    final response = await _client.post(Uri.parse('${AppConfig.apiBaseUrl}/sync/renew-lease'), headers: _buildHeaders(), body: json.encode({'device_uuid': deviceUuid})).timeout(const Duration(seconds: 15));
    if (response.statusCode != 200) throw const ServerException('Qurilma ruxsatini yangilab bo‘lmadi. Internetga ulanib qayta urinib ko‘ring.');
    final lease = (json.decode(response.body) as Map<String, dynamic>)['data'] as Map<String, dynamic>;
    final expires = DateTime.tryParse(lease['expires_at'] as String? ?? '');
    if (expires == null || !expires.isAfter(DateTime.now()) || (lease['lease_token'] as String? ?? '').isEmpty) throw const ServerException('Server yaroqli qurilma ruxsatini qaytarmadi.');
    await db.update('offline_leases', {
      'lease_token': lease['lease_token'], 'valid_from': lease['valid_from'], 'expires_at': lease['expires_at'],
      'permissions': json.encode(lease['permissions'] ?? []), 'epoch': lease['epoch'], 'signature': lease['signature'],
    }, where: 'user_id = ? AND device_uuid = ? AND is_active = 1', whereArgs: [user.id, deviceUuid]);
    return true;
  }

  Future<void> syncNow() async {
    if (_isSyncing) return;
    _isSyncing = true;
    _lastError = null;
    notifyListeners();

    try {
      final isOnline = await checkHealth();
      if (!isOnline) {
        _lastError = "Serverga ulanib bo'lmadi (Offline).";
        return;
      }

      if (await _checkRecoveryState()) return;
      await pushPendingOperations();
      final db = await _appDb.database;
      final hold = await db.query(
        'app_meta',
        where: 'key = ?',
        whereArgs: ['recovery_hold'],
      );
      if (hold.isNotEmpty && hold.first['value'] == 'true') return;
      await renewDeviceLease();
      await pullDeltaChanges();
    } catch (e) {
      _lastError = e.toString();
    } finally {
      _isSyncing = false;
      notifyListeners();
    }
  }

  /// Navbat holati xulosasi (Summary)
  Future<SyncStatusSummary> getStatusSummary() async {
    final user = SessionService().currentUser;
    if (user == null) {
      return const SyncStatusSummary();
    }

    final db = await _appDb.database;

    final pending =
        Sqflite.firstIntValue(
          await db.rawQuery(
            "SELECT COUNT(*) FROM sync_queue WHERE user_id = ? AND status = 'PENDING'",
            [user.id],
          ),
        ) ??
        0;

    final ack =
        Sqflite.firstIntValue(
          await db.rawQuery(
            "SELECT COUNT(*) FROM sync_queue WHERE user_id = ? AND status = 'ACKNOWLEDGED'",
            [user.id],
          ),
        ) ??
        0;

    final needsReview =
        Sqflite.firstIntValue(
          await db.rawQuery(
            "SELECT COUNT(*) FROM sync_queue WHERE user_id = ? AND status = 'NEEDS_REVIEW'",
            [user.id],
          ),
        ) ??
        0;

    final conflict =
        Sqflite.firstIntValue(
          await db.rawQuery(
            "SELECT COUNT(*) FROM sync_queue WHERE user_id = ? AND status = 'CONFLICT'",
            [user.id],
          ),
        ) ??
        0;

    return SyncStatusSummary(
      pendingCount: pending,
      acknowledgedCount: ack,
      needsReviewCount: needsReview,
      conflictCount: conflict,
      isSyncing: _isSyncing,
      lastError: _lastError,
      lastSyncedAt: _lastSyncedAt,
    );
  }

  /// NEEDS_REVIEW yozuvlari ro'yxati
  Future<List<SyncQueueItem>> getNeedsReviewItems() async {
    final user = SessionService().currentUser;
    if (user == null) return [];

    final db = await _appDb.database;
    final rows = await db.query(
      'sync_queue',
      where: 'user_id = ? AND status = ?',
      whereArgs: [user.id, 'NEEDS_REVIEW'],
      orderBy: 'device_created_at DESC',
    );

    return rows.map((r) => SyncQueueItem.fromDbMap(r)).toList();
  }
}
