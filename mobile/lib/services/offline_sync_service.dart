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
  String get deviceUuid => _deviceUuid ?? 'mobile-device-android-01';

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
      body: json.encode({'device_uuid': deviceUuid}),
    );

    if (response.statusCode != 200) {
      throw ServerException("Bootstrap muvaffaqiyatsiz tugadi (${response.statusCode})");
    }

    final data = (json.decode(response.body)['data'] as Map<String, dynamic>?) ?? {};
    final db = await _appDb.database;

    await db.transaction((txn) async {
      // 1. Lease saqlash
      final leaseData = data['lease'] as Map<String, dynamic>?;
      if (leaseData != null) {
        await txn.delete('offline_leases',
            where: 'user_id = ?', whereArgs: [user.id]);
        await txn.insert('offline_leases', {
          'device_uuid': deviceUuid,
          'user_id': user.id,
          'lease_token': leaseData['lease_token'] ?? '',
          'valid_from': leaseData['valid_from'] ?? DateTime.now().toIso8601String(),
          'expires_at': leaseData['expires_at'] ?? DateTime.now().add(const Duration(hours: 24)).toIso8601String(),
          'permissions': json.encode(leaseData['permissions'] ?? []),
          'epoch': leaseData['epoch'] ?? 1,
          'signature': leaseData['signature'] ?? '',
          'is_active': 1,
        });
      }

      // 2. Tovar ajratmalari (Stock Allocations)
      final stockAllocations = (data['stock_allocations'] as List<dynamic>?) ?? [];
      for (final alloc in stockAllocations) {
        final a = alloc as Map<String, dynamic>;
        await txn.insert(
          'stock_allocations',
          {
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
          },
          conflictAlgorithm: ConflictAlgorithm.replace,
        );
      }

      // 3. Kredit ajratmalari (Credit Allocations)
      final creditAllocations = (data['credit_allocations'] as List<dynamic>?) ?? [];
      for (final alloc in creditAllocations) {
        final a = alloc as Map<String, dynamic>;
        await txn.insert(
          'credit_allocations',
          {
            'id': a['allocation_id'] ?? a['id'],
            'customer_id': a['customer_id'],
            'customer_name': a['customer_name'] ?? '',
            'allocated_amount': a['allocated_amount'] ?? 0,
            'consumed_amount': a['consumed_amount'] ?? 0,
            'returned_amount': a['returned_amount'] ?? 0,
            'available_amount': a['available_amount'] ?? 0,
            'updated_at': DateTime.now().toIso8601String(),
          },
          conflictAlgorithm: ConflictAlgorithm.replace,
        );
      }

      // 4. Mahsulotlar katalogi
      final products = (data['products'] as List<dynamic>?) ?? [];
      for (final prod in products) {
        final p = prod as Map<String, dynamic>;
        await txn.insert(
          'products',
          {
            'id': p['id'],
            'name': p['name'],
            'code': p['code'] ?? '',
            'is_active': (p['is_active'] == true || p['is_active'] == 1) ? 1 : 0,
            'updated_at': DateTime.now().toIso8601String(),
          },
          conflictAlgorithm: ConflictAlgorithm.replace,
        );

        final variants = (p['variants'] as List<dynamic>?) ?? [];
        for (final v in variants) {
          final vr = v as Map<String, dynamic>;
          await txn.insert(
            'product_variants',
            {
              'id': vr['id'],
              'product_id': p['id'],
              'sku': vr['sku'] ?? '',
              'litres': (vr['litres'] as num?)?.toDouble() ?? 0.5,
              'volume_ml': (vr['volume_ml'] as num?)?.toInt() ?? 500,
              'display_volume': vr['display_volume'] ?? '',
              'stock_qty': (vr['stock_qty'] as num?)?.toInt() ?? 0,
              'cost_price': (vr['cost_price'] as num?)?.toInt(),
              'retail_price': (vr['retail_price'] as num?)?.toInt() ?? 0,
              'default_sale_price': (vr['default_sale_price'] as num?)?.toInt() ?? 0,
              'updated_at': DateTime.now().toIso8601String(),
            },
            conflictAlgorithm: ConflictAlgorithm.replace,
          );
        }
      }

      // 5. Mijozlar
      final customers = (data['customers'] as List<dynamic>?) ?? [];
      for (final cust in customers) {
        final c = cust as Map<String, dynamic>;
        await txn.insert(
          'customers',
          {
            'id': c['id'],
            'uuid': c['uuid'],
            'name': c['name'],
            'phone': c['phone'],
            'store_name': c['store_name'],
            'address': c['address'],
            'current_debt': (c['current_debt'] as num?)?.toInt() ?? 0,
            'debt_limit': (c['debt_limit'] as num?)?.toInt() ?? 0,
            'is_active': (c['is_active'] == true || c['is_active'] == 1) ? 1 : 0,
            'created_offline': 0,
            'updated_at': DateTime.now().toIso8601String(),
          },
          conflictAlgorithm: ConflictAlgorithm.replace,
        );
      }

      // 6. Kassa hisoblari
      final cashAccounts = (data['cash_accounts'] as List<dynamic>?) ?? [];
      for (final acc in cashAccounts) {
        final a = acc as Map<String, dynamic>;
        await txn.insert(
          'cash_accounts',
          {
            'id': a['id'],
            'name': a['name'],
            'type': a['type'] ?? 'CASH',
            'balance': (a['balance'] as num?)?.toInt() ?? 0,
            'is_default': (a['is_default'] == true || a['is_default'] == 1) ? 1 : 0,
            'is_active': (a['is_active'] == true || a['is_active'] == 1) ? 1 : 0,
            'updated_at': DateTime.now().toIso8601String(),
          },
          conflictAlgorithm: ConflictAlgorithm.replace,
        );
      }

      // 7. Kursor
      final cursorVal = (data['cursor'] as num?)?.toInt() ?? 0;
      await txn.insert(
        'sync_cursor',
        {
          'id': 1,
          'cursor_pos': cursorVal,
          'last_synced_at': DateTime.now().toIso8601String(),
        },
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    });

    _lastSyncedAt = DateTime.now();
    notifyListeners();
    return data;
  }

  /// Kutilayotgan operatsiyalarni serverga yuborish (Push Pending Operations)
  Future<int> pushPendingOperations({String? specificWorkerId, String? deviceUuid}) async {
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
      await txn.rawUpdate('''
        UPDATE sync_queue 
        SET locked_until = NULL, worker_id = NULL 
        WHERE status = 'PENDING' AND locked_until IS NOT NULL AND locked_until < ?
      ''', [nowIso]);

      // Faqat ayni user_id ga tegishli PENDING yozuvlarni qulflash (User Isolation!)
      await txn.rawUpdate('''
        UPDATE sync_queue 
        SET locked_until = ?, worker_id = ? 
        WHERE user_id = ? AND status = 'PENDING' AND (locked_until IS NULL OR locked_until < ?)
      ''', [lockExpiry.toIso8601String(), workerId, user.id, nowIso]);

      final rows = await txn.query(
        'sync_queue',
        where: 'user_id = ? AND worker_id = ? AND status = ?',
        whereArgs: [user.id, workerId, 'PENDING'],
        orderBy: "CASE type WHEN 'CREATE_CUSTOMER' THEN 1 WHEN 'CREATE_SALE' THEN 2 ELSE 3 END, device_created_at ASC",
      );

      lockedItems = rows.map((r) => SyncQueueItem.fromDbMap(r)).toList();
    });

    if (lockedItems.isEmpty) {
      return 0;
    }

    final effectiveDeviceUuid = deviceUuid ?? lockedItems.first.deviceUuid;

    // 2. Batch payload tayyorlash
    final pushOperations = lockedItems.map((item) => item.toPushOperation()).toList();
    final firstLeaseToken = lockedItems.first.leaseToken;

    final headers = _buildHeaders();
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

      if (response.statusCode >= 200 && response.statusCode < 300) {
        final resData = json.decode(response.body) as Map<String, dynamic>;
        final results = (resData['results'] as List<dynamic>?) ?? [];

        await db.transaction((txn) async {
          for (final rawRes in results) {
            final res = rawRes as Map<String, dynamic>;
            final opId = res['operation_id'] as String?;
            if (opId == null) continue;

            final status = (res['status'] as String?)?.toUpperCase() ?? 'PENDING';
            final serverDocId = (res['server_document_id'] as num?)?.toInt();
            final serverDocNum = res['server_document_number'] as String?;
            final errorCode = res['error_code'] as String?;
            final errorMsg = res['message'] as String?;

            if (status == 'SUCCESS' || status == 'RETRY_SUCCESS') {
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
              await txn.rawUpdate('''
                UPDATE sync_queue 
                SET retry_count = retry_count + 1, error_code = ?, error_message = ?, locked_until = NULL, worker_id = NULL 
                WHERE operation_id = ?
              ''', [errorCode, errorMsg, opId]);
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
    await db.rawUpdate('''
      UPDATE sync_queue 
      SET locked_until = NULL, worker_id = NULL 
      WHERE worker_id = ?
    ''', [workerId]);
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

    final uri = Uri.parse('${AppConfig.apiBaseUrl}/sync/pull')
        .replace(queryParameters: {'cursor': curPos.toString(), 'limit': '50'});

    final response = await _client.get(uri, headers: _buildHeaders());
    if (response.statusCode != 200) {
      return 0;
    }

    final data = (json.decode(response.body)['data'] as Map<String, dynamic>?) ?? {};
    final newCursor = (data['cursor'] as num?)?.toInt() ?? curPos;
    final changes = (data['changes'] as List<dynamic>?) ?? [];

    if (changes.isNotEmpty) {
      await db.transaction((txn) async {
        for (final item in changes) {
          final ch = item as Map<String, dynamic>;
          final entity = ch['entity_type'] as String?;
          final payload = ch['payload'] as Map<String, dynamic>? ?? {};

          if (entity == 'product_variant') {
            final vId = (ch['entity_id'] as num?)?.toInt() ?? (payload['id'] as num?)?.toInt();
            if (vId != null) {
              await txn.rawUpdate('''
                UPDATE product_variants 
                SET stock_qty = ?, retail_price = ?, default_sale_price = ?, updated_at = ? 
                WHERE id = ?
              ''', [
                payload['stock_qty'] ?? 0,
                payload['retail_price'] ?? 0,
                payload['default_sale_price'] ?? 0,
                DateTime.now().toIso8601String(),
                vId,
              ]);
            }
          } else if (entity == 'customer') {
            final cId = (ch['entity_id'] as num?)?.toInt() ?? (payload['id'] as num?)?.toInt();
            if (cId != null) {
              await txn.rawUpdate('''
                UPDATE customers 
                SET current_debt = ?, debt_limit = ?, updated_at = ? 
                WHERE id = ?
              ''', [
                payload['current_debt'] ?? 0,
                payload['debt_limit'] ?? 0,
                DateTime.now().toIso8601String(),
                cId,
              ]);
            }
          }
        }

        await txn.insert(
          'sync_cursor',
          {
            'id': 1,
            'cursor_pos': newCursor,
            'last_synced_at': DateTime.now().toIso8601String(),
          },
          conflictAlgorithm: ConflictAlgorithm.replace,
        );
      });
    }

    _lastSyncedAt = DateTime.now();
    notifyListeners();
    return changes.length;
  }

  /// Qo'lda yoki avtomatik to'liq sinxronlash (Sync Now)
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

      await pushPendingOperations();
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

    final pending = Sqflite.firstIntValue(await db.rawQuery(
      "SELECT COUNT(*) FROM sync_queue WHERE user_id = ? AND status = 'PENDING'",
      [user.id],
    )) ?? 0;

    final ack = Sqflite.firstIntValue(await db.rawQuery(
      "SELECT COUNT(*) FROM sync_queue WHERE user_id = ? AND status = 'ACKNOWLEDGED'",
      [user.id],
    )) ?? 0;

    final needsReview = Sqflite.firstIntValue(await db.rawQuery(
      "SELECT COUNT(*) FROM sync_queue WHERE user_id = ? AND status = 'NEEDS_REVIEW'",
      [user.id],
    )) ?? 0;

    final conflict = Sqflite.firstIntValue(await db.rawQuery(
      "SELECT COUNT(*) FROM sync_queue WHERE user_id = ? AND status = 'CONFLICT'",
      [user.id],
    )) ?? 0;

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
