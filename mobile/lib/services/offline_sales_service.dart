import 'dart:convert';
import 'package:sqflite/sqflite.dart';
import '../database/app_database.dart';
import '../models/customer_model.dart';
import '../models/offline_lease_model.dart';
import '../models/user_model.dart';
import '../utils/operation_id.dart';
import '../utils/payload_fingerprint.dart';
import 'api_exceptions.dart';
import 'session_service.dart';

class LocalSaleConfirmResult {
  final String operationId;
  final String tempInvoiceNumber;
  final int totalAmount;
  final int paidAmount;
  final int debtAmount;
  final Map<String, dynamic> receipt;

  const LocalSaleConfirmResult({
    required this.operationId,
    required this.tempInvoiceNumber,
    required this.totalAmount,
    required this.paidAmount,
    required this.debtAmount,
    required this.receipt,
  });
}

class OfflineSalesService {
  final AppDatabase _appDb;

  OfflineSalesService({AppDatabase? appDb}) : _appDb = appDb ?? AppDatabase();

  /// Qurilmaning faol lease ruxsatnomasini tekshirish
  Future<OfflineLeaseModel> getActiveLease({int? userId}) async {
    final db = await _appDb.database;
    final hold = await db.query(
      'app_meta',
      where: 'key = ?',
      whereArgs: ['recovery_hold'],
    );
    if (hold.isNotEmpty && hold.first['value'] == 'true') {
      throw const ForbiddenException(
        'Recovery tekshiruvi tugamaguncha yangi savdo to‘xtatilgan.',
      );
    }
    final currentUserId = userId ?? SessionService().currentUser?.id ?? 0;

    final rows = await db.query(
      'offline_leases',
      where: 'user_id = ? AND is_active = 1',
      whereArgs: [currentUserId],
      orderBy: 'id DESC',
      limit: 1,
    );

    if (rows.isEmpty) {
      throw const ForbiddenException(
        "Qurilmada offline lease topilmadi! Avval kamida bir marta online kirib bootstrap qiling.",
      );
    }

    final row = rows.first;
    final permissionsRaw = row['permissions'] as String? ?? '[]';
    final perms = (json.decode(permissionsRaw) as List<dynamic>)
        .map((e) => e.toString())
        .toList();

    final lease = OfflineLeaseModel(
      deviceUuid: row['device_uuid'] as String,
      userId: (row['user_id'] as num).toInt(),
      leaseToken: row['lease_token'] as String,
      validFrom: DateTime.parse(row['valid_from'] as String).toUtc(),
      expiresAt: DateTime.parse(row['expires_at'] as String).toUtc(),
      permissions: perms,
      epoch: (row['epoch'] as num?)?.toInt() ?? 1,
      signature: row['signature'] as String? ?? '',
    );

    if (lease.isExpired) {
      throw const ForbiddenException(
        "Offline lease muddati tugagan (LEASE_EXPIRED)! Yangi sotuv uchun online bo'ling.",
      );
    }

    if (!lease.canSell) {
      throw const ForbiddenException("Sizda offline savdo qilish huquqi yo'q!");
    }

    return lease;
  }

  /// Atomik lokal savdo tasdiqlash (Local Atomic Confirm)
  Future<LocalSaleConfirmResult> confirmSaleOffline({
    required List<Map<String, dynamic>> items,
    int? customerId,
    String? customerUuid,
    String? customerName,
    required String paymentType, // CASH, CARD, BANK, PARTIAL, DEBT
    required String paymentMethod,
    int? paidAmount,
    int? cashAccountId,
    String? operationId,
    String? notes,
    UserModel? user,
  }) async {
    final currentUser = user ?? SessionService().currentUser;
    if (currentUser == null) {
      throw const UnauthorizedException("Foydalanuvchi tizimga kirmagan.");
    }

    final lease = await getActiveLease(userId: currentUser.id);
    final opId = operationId ?? OperationId.generate();
    final db = await _appDb.database;

    if (items.isEmpty) {
      throw ValidationException("Savat bo'sh! Kamida bitta tovar tanlang.");
    }

    return await db.transaction((txn) async {
      int calculatedTotal = 0;
      final receiptItems = <Map<String, dynamic>>[];
      final cleanPayloadItems = <Map<String, dynamic>>[];

      // 1. Har bir tovar ajratmasini (Stock Allocation) tekshirish va band qilish
      for (final item in items) {
        final variantId = (item['variant_id'] as num).toInt();
        final qty = (item['quantity'] as num).toInt();
        final unitPrice = ((item['sale_price'] ?? item['unit_price']) as num)
            .toInt();
        final lineTotal = qty * unitPrice;
        calculatedTotal += lineTotal;

        final allocRows = await txn.query(
          'stock_allocations',
          where: 'variant_id = ?',
          whereArgs: [variantId],
          limit: 1,
        );

        if (allocRows.isEmpty) {
          throw ValidationException(
            "Tovar (variant #$variantId) uchun ushbu qurilmaga ombor ajratmasi berilmagan!",
          );
        }

        final alloc = allocRows.first;
        final availableQty = (alloc['available_quantity'] as num).toInt();
        if (availableQty < qty) {
          final prodName = alloc['product_name'] ?? 'Tovar';
          throw ValidationException(
            "Qurilmada '$prodName' uchun yetarli tovar ajratilmagan! Mavjud ajratma: $availableQty dona, So'ralgan: $qty dona.",
          );
        }

        // Ajratmani mahalliy kamaytirish
        final newConsumed = (alloc['consumed_quantity'] as num).toInt() + qty;
        final newAvailable = availableQty - qty;

        await txn.update(
          'stock_allocations',
          {
            'consumed_quantity': newConsumed,
            'available_quantity': newAvailable,
            'updated_at': DateTime.now().toUtc().toIso8601String(),
          },
          where: 'variant_id = ?',
          whereArgs: [variantId],
        );

        receiptItems.add({
          'variant_id': variantId,
          'product_name':
              alloc['product_name'] ?? item['product_name'] ?? 'Tovar',
          'volume_name': alloc['volume_name'] ?? '',
          'quantity': qty,
          'unit_price': unitPrice,
          'total_price': lineTotal,
        });

        cleanPayloadItems.add({
          'variant_id': variantId,
          'quantity': qty,
          'sale_price': unitPrice,
        });
      }

      // 2. To'lov va Qarz hisob-kitobi
      int finalPaid = 0;
      int finalDebt = 0;

      if (paymentType == 'FULL' ||
          paymentType == 'CASH' ||
          paymentType == 'CARD') {
        finalPaid = calculatedTotal;
        finalDebt = 0;
      } else if (paymentType == 'DEBT') {
        finalPaid = 0;
        finalDebt = calculatedTotal;
      } else {
        // PARTIAL
        final entered = paidAmount ?? 0;
        finalPaid = entered.clamp(0, calculatedTotal);
        finalDebt = calculatedTotal - finalPaid;
      }

      // Nasiya tekshiruvi
      if (finalDebt > 0) {
        if (customerId == null &&
            (customerUuid == null || customerUuid.isEmpty)) {
          throw ValidationException(
            "Nasiya yoki qisman to'lov uchun xaridorni tanlash shart!",
          );
        }

        if (customerId != null) {
          final custAllocRows = await txn.query(
            'credit_allocations',
            where: 'customer_id = ?',
            whereArgs: [customerId],
            limit: 1,
          );

          if (custAllocRows.isNotEmpty) {
            final custAlloc = custAllocRows.first;
            final availCredit = (custAlloc['available_amount'] as num).toInt();
            if (availCredit < finalDebt) {
              throw ValidationException(
                "Mijoz uchun kredit ajratmasi yetarli emas! Mavjud limit: $availCredit so'm, Talab: $finalDebt so'm.",
              );
            }

            // Kredit ajratmasini mahalliy kamaytirish
            final newConsumed =
                (custAlloc['consumed_amount'] as num).toInt() + finalDebt;
            final newAvail = availCredit - finalDebt;
            await txn.update(
              'credit_allocations',
              {
                'consumed_amount': newConsumed,
                'available_amount': newAvail,
                'updated_at': DateTime.now().toUtc().toIso8601String(),
              },
              where: 'customer_id = ?',
              whereArgs: [customerId],
            );
          }

          // Mijozning lokal qarzini oshirish
          final custRows = await txn.query(
            'customers',
            where: 'id = ?',
            whereArgs: [customerId],
            limit: 1,
          );
          if (custRows.isNotEmpty) {
            final curDebt =
                (custRows.first['current_debt'] as num?)?.toInt() ?? 0;
            await txn.update(
              'customers',
              {'current_debt': curDebt + finalDebt},
              where: 'id = ?',
              whereArgs: [customerId],
            );
          }
        }
      }

      // 3. Vaqtinchalik mahalliy chek raqami
      final tempInvoiceNumber =
          '#OFF-${DateTime.now().millisecondsSinceEpoch.toString().substring(5)}';

      // 4. Barqaror Payload va Kanonik Fingerprint
      final payload = <String, dynamic>{
        'items': cleanPayloadItems,
        'payment_type': paymentType,
        'payment_method': paymentMethod,
        'paid_amount': finalPaid,
        'operation_id': opId,
      };

      if (customerId != null) payload['customer_id'] = customerId;
      if (customerUuid != null && customerUuid.isNotEmpty) {
        payload['customer_uuid'] = customerUuid;
      }
      if (customerName != null && customerName.isNotEmpty) {
        payload['customer_name'] = customerName;
      }
      if (cashAccountId != null) payload['cash_account_id'] = cashAccountId;
      if (notes != null && notes.isNotEmpty) payload['notes'] = notes;

      final fingerprint = PayloadFingerprint.compute(payload);

      // 5. Sync navbatiga atomik yozish
      final queueMap = {
        'operation_id': opId,
        'user_id': currentUser.id,
        'device_uuid': lease.deviceUuid,
        'type': 'CREATE_SALE',
        'payload': json.encode(payload),
        'payload_fingerprint': fingerprint,
        'status': 'PENDING',
        'retry_count': 0,
        'server_document_number': tempInvoiceNumber,
        'device_created_at': DateTime.now().toUtc().toIso8601String(),
        'lease_token': lease.leaseToken,
      };

      await txn.insert(
        'sync_queue',
        queueMap,
        conflictAlgorithm: ConflictAlgorithm.replace,
      );

      final receipt = {
        'store_name': 'AquaOptom CRM (Offline)',
        'sale_number': tempInvoiceNumber,
        'is_offline_receipt': true,
        'operation_id': opId,
        'customer_name': customerName ?? 'Tezkor Mijoz',
        'created_at': DateTime.now().toIso8601String(),
        'total_amount': calculatedTotal,
        'paid_amount': finalPaid,
        'debt_amount': finalDebt,
        'payment_method': paymentMethod,
        'items': receiptItems,
      };

      return LocalSaleConfirmResult(
        operationId: opId,
        tempInvoiceNumber: tempInvoiceNumber,
        totalAmount: calculatedTotal,
        paidAmount: finalPaid,
        debtAmount: finalDebt,
        receipt: receipt,
      );
    });
  }

  /// Offline yangi mijoz yaratish (UUID Parent mapping)
  Future<CustomerModel> createCustomerOffline({
    required String name,
    String? phone,
    String? storeName,
    String? address,
    int debtLimit = 0,
    UserModel? user,
  }) async {
    final currentUser = user ?? SessionService().currentUser;
    final lease = await getActiveLease(userId: currentUser?.id);
    final customerUuid = OperationId.generate();
    final opId = OperationId.generate();
    final db = await _appDb.database;

    final customerData = {
      'uuid': customerUuid,
      'name': name.trim(),
      'phone': phone?.trim(),
      'store_name': storeName?.trim(),
      'address': address?.trim(),
      'current_debt': 0,
      'debt_limit': debtLimit,
      'is_active': 1,
      'created_offline': 1,
      'updated_at': DateTime.now().toUtc().toIso8601String(),
    };

    final payload = {
      'customer_uuid': customerUuid,
      'name': name.trim(),
      'phone': phone?.trim(),
      'store_name': storeName?.trim(),
      'address': address?.trim(),
      'debt_limit': debtLimit,
      'operation_id': opId,
    };

    final fingerprint = PayloadFingerprint.compute(payload);

    await db.transaction((txn) async {
      final localId = await txn.insert('customers', customerData);

      await txn.insert('sync_queue', {
        'operation_id': opId,
        'user_id': currentUser?.id ?? 0,
        'device_uuid': lease.deviceUuid,
        'type': 'CREATE_CUSTOMER',
        'payload': json.encode(payload),
        'payload_fingerprint': fingerprint,
        'status': 'PENDING',
        'retry_count': 0,
        'device_created_at': DateTime.now().toUtc().toIso8601String(),
        'lease_token': lease.leaseToken,
      });

      customerData['id'] = localId;
    });

    final cleanName = name.trim();
    final cleanStore = storeName?.trim();
    return CustomerModel(
      id: 0, // Server hali ID bermagan
      name: cleanName,
      displayName: (cleanStore != null && cleanStore.isNotEmpty)
          ? '$cleanName ($cleanStore)'
          : cleanName,
      phone: phone?.trim(),
      storeName: cleanStore,
      address: address?.trim(),
      currentDebt: 0,
      debtLimit: debtLimit,
      uuid: customerUuid,
    );
  }

  /// Offline bekor qilish (Void / Cancel sale): Originalga bog'langan tuzatish yoziladi, navbatdan o'chirilmaydi!
  Future<String> voidSaleOffline({
    required String originalOperationId,
    String? reason,
    UserModel? user,
  }) async {
    final currentUser = user ?? SessionService().currentUser;
    final lease = await getActiveLease(userId: currentUser?.id);
    final voidOpId = OperationId.generate();
    final db = await _appDb.database;

    return await db.transaction((txn) async {
      final rows = await txn.query(
        'sync_queue',
        where: 'operation_id = ?',
        whereArgs: [originalOperationId],
        limit: 1,
      );

      if (rows.isEmpty) {
        throw ValidationException(
          "Asl savdo (#$originalOperationId) topilmadi!",
        );
      }

      final origRow = rows.first;
      final origPayload =
          json.decode(origRow['payload'] as String) as Map<String, dynamic>;
      final origItems = (origPayload['items'] as List<dynamic>?) ?? [];

      // Tovarlar ajratmasini orqaga qaytarish (Re-credit stock allocation)
      for (final it in origItems) {
        final vId = (it['variant_id'] as num).toInt();
        final qty = (it['quantity'] as num).toInt();

        final allocs = await txn.query(
          'stock_allocations',
          where: 'variant_id = ?',
          whereArgs: [vId],
          limit: 1,
        );
        if (allocs.isNotEmpty) {
          final alloc = allocs.first;
          final curConsumed = (alloc['consumed_quantity'] as num).toInt();
          final curAvail = (alloc['available_quantity'] as num).toInt();
          await txn.update(
            'stock_allocations',
            {
              'consumed_quantity': (curConsumed - qty).clamp(0, curConsumed),
              'available_quantity': curAvail + qty,
            },
            where: 'variant_id = ?',
            whereArgs: [vId],
          );
        }
      }

      // Qarz ajratmasini orqaga qaytarish
      final origDebt = (origPayload['debt_amount'] as num?)?.toInt() ?? 0;
      final custId = (origPayload['customer_id'] as num?)?.toInt();
      if (origDebt > 0 && custId != null) {
        final custAllocs = await txn.query(
          'credit_allocations',
          where: 'customer_id = ?',
          whereArgs: [custId],
          limit: 1,
        );
        if (custAllocs.isNotEmpty) {
          final alloc = custAllocs.first;
          final curConsumed = (alloc['consumed_amount'] as num).toInt();
          final curAvail = (alloc['available_amount'] as num).toInt();
          await txn.update(
            'credit_allocations',
            {
              'consumed_amount': (curConsumed - origDebt).clamp(0, curConsumed),
              'available_amount': curAvail + origDebt,
            },
            where: 'customer_id = ?',
            whereArgs: [custId],
          );
        }
      }

      final voidPayload = {
        'original_operation_id': originalOperationId,
        'reason': reason ?? 'Offline savdo bekor qilindi',
        'void_operation_id': voidOpId,
      };

      final fingerprint = PayloadFingerprint.compute(voidPayload);

      // Original yozuv navbatdan o'chirilmaydi! Alohida VOID_SALE yoziladi.
      await txn.insert('sync_queue', {
        'operation_id': voidOpId,
        'user_id': currentUser?.id ?? 0,
        'device_uuid': lease.deviceUuid,
        'type': 'VOID_SALE',
        'payload': json.encode(voidPayload),
        'payload_fingerprint': fingerprint,
        'status': 'PENDING',
        'retry_count': 0,
        'device_created_at': DateTime.now().toUtc().toIso8601String(),
        'lease_token': lease.leaseToken,
      });

      return voidOpId;
    });
  }
}
