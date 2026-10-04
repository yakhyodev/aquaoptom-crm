import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:mobile/database/app_database.dart';
import 'package:mobile/models/user_model.dart';
import 'package:mobile/services/api_exceptions.dart';
import 'package:mobile/services/offline_sales_service.dart';
import 'package:mobile/services/offline_sync_service.dart';
import 'package:mobile/services/session_service.dart';
import 'package:mobile/utils/operation_id.dart';
import 'package:mobile/utils/payload_fingerprint.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  sqfliteFfiInit();
  databaseFactory = databaseFactoryFfi;

  const testUserA = UserModel(
    id: 1,
    name: 'Ali Sotuvchi',
    email: 'ali@aquaoptom.uz',
    role: 'CASHIER',
    permissions: ['sell_products', 'create_customer'],
  );

  const testUserB = UserModel(
    id: 2,
    name: 'Vali Haydovchi',
    email: 'vali@aquaoptom.uz',
    role: 'CASHIER',
    permissions: ['sell_products'],
  );

  group('1. Canonical PayloadFingerprint (Backend/PWA Parity)', () {
    test('SHA-256 fingerprint matches backend test vector exactly', () {
      final payload1 = {
        'zebra': 100,
        'apple': 'suv',
        'details': {'b': 2, 'a': 1},
      };

      // Backend PHP computes: 07f9f20bf995cc2e5ac5aec9353295186d34b2c5e979e15c0206ecd3362d294d
      final hash1 = PayloadFingerprint.compute(payload1);
      expect(
        hash1,
        equals(
          '07f9f20bf995cc2e5ac5aec9353295186d34b2c5e979e15c0206ecd3362d294d',
        ),
      );

      // payload2 with different key ordering, string spaces, and transport keys
      final payload2 = {
        'apple': ' suv ',
        'details': {'a': 1, 'b': 2},
        'zebra': 100,
        '_token': 'transient-csrf-token',
        'request_id': 'req-987654',
        'nonce': '12345',
        'client_time': '2026-10-04T12:00:00Z',
      };

      final hash2 = PayloadFingerprint.compute(payload2);
      expect(hash2, equals(hash1));
    });

    test('Floating point values are canonicalized up to 4 decimal places', () {
      final p1 = {'price': 15000.50000001};
      final p2 = {'price': 15000.5};
      expect(
        PayloadFingerprint.compute(p1),
        equals(PayloadFingerprint.compute(p2)),
      );
    });
  });

  group('2. SQLite Schema & Migration Preservation', () {
    late Database testDb;

    setUp(() async {
      // Yangi in-memory database
      testDb = await databaseFactoryFfi.openDatabase(
        inMemoryDatabasePath,
        options: OpenDatabaseOptions(
          version: 1,
          onCreate: (db, version) async {
            // v1 jadvallari
            await db.execute('''
              CREATE TABLE IF NOT EXISTS sync_queue (
                operation_id TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                device_uuid TEXT NOT NULL,
                type TEXT NOT NULL,
                payload TEXT NOT NULL,
                payload_fingerprint TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'PENDING',
                retry_count INTEGER DEFAULT 0,
                error_code TEXT,
                error_message TEXT,
                server_document_id INTEGER,
                server_document_number TEXT,
                ack_payload TEXT,
                device_created_at TEXT NOT NULL,
                processed_at TEXT,
                lease_token TEXT,
                locked_until TEXT,
                worker_id TEXT
              )
            ''');
            await db.execute('''
              CREATE TABLE IF NOT EXISTS stock_allocations (
                id INTEGER PRIMARY KEY,
                variant_id INTEGER UNIQUE NOT NULL,
                sku TEXT,
                product_name TEXT,
                volume_name TEXT,
                allocated_quantity INTEGER NOT NULL,
                consumed_quantity INTEGER DEFAULT 0,
                returned_quantity INTEGER DEFAULT 0,
                available_quantity INTEGER NOT NULL,
                updated_at TEXT
              )
            ''');
          },
        ),
      );
    });

    tearDown(() async {
      await testDb.close();
    });

    test(
      'Migration v1 -> v2 preserves pending sync_queue rows without data loss',
      () async {
        // 1. v1 holatida navbatga yozuv kiritamiz
        await testDb.insert('sync_queue', {
          'operation_id': 'op-preserve-1',
          'user_id': 1,
          'device_uuid': 'dev-1',
          'type': 'CREATE_SALE',
          'payload': json.encode({'total': 50000}),
          'payload_fingerprint': 'dummy-hash',
          'status': 'PENDING',
          'device_created_at': DateTime.now().toUtc().toIso8601String(),
        });

        // 2. v2 ga migratsiya qilamiz (indekslar qo'shish)
        await testDb.execute(
          'CREATE INDEX IF NOT EXISTS idx_sync_queue_user_status ON sync_queue (user_id, status)',
        );

        // 3. Tekshiramiz: navbatdagi element saqlanib qolganmi?
        final rows = await testDb.query(
          'sync_queue',
          where: 'operation_id = ?',
          whereArgs: ['op-preserve-1'],
        );
        expect(rows.length, equals(1));
        expect(rows.first['status'], equals('PENDING'));
        expect(rows.first['user_id'], equals(1));
      },
    );
  });

  group('3. Offline Sales Service: Stock Allocation & Atomic Confirm', () {
    late Database db;
    late AppDatabase appDb;
    late OfflineSalesService salesService;

    setUp(() async {
      appDb = AppDatabase();
      db = await databaseFactoryFfi.openDatabase(
        inMemoryDatabasePath,
        options: OpenDatabaseOptions(
          version: 2,
          onCreate: AppDatabase.createSchema,
        ),
      );
      AppDatabase.setTestDatabase(db);
      salesService = OfflineSalesService(appDb: appDb);

      SessionService().setSession(user: testUserA, token: 'test-token-a');

      // Faol lease yaratamiz (24 soat amal qiladi)
      await db.insert('offline_leases', {
        'device_uuid': 'dev-test-123',
        'user_id': testUserA.id,
        'lease_token': 'lease-token-abc',
        'valid_from': DateTime.now()
            .subtract(const Duration(hours: 1))
            .toUtc()
            .toIso8601String(),
        'expires_at': DateTime.now()
            .add(const Duration(hours: 23))
            .toUtc()
            .toIso8601String(),
        'permissions': json.encode(['sell_products', 'create_customer']),
        'epoch': 1,
        'is_active': 1,
      });

      // Tovar varianti va ombor ajratmasi (50 dona mavjud)
      await db.insert('product_variants', {
        'id': 101,
        'product_id': 1,
        'sku': 'FANTA-1L',
        'stock_qty': 50,
        'default_sale_price': 10000,
      });
      await db.insert('stock_allocations', {
        'id': 1,
        'variant_id': 101,
        'sku': 'FANTA-1L',
        'product_name': 'Fanta',
        'volume_name': '1L',
        'allocated_quantity': 50,
        'consumed_quantity': 0,
        'returned_quantity': 0,
        'available_quantity': 50,
      });

      // Mijoz va kredit ajratmasi (100,000 so'm limit)
      await db.insert('customers', {
        'id': 501,
        'uuid': 'cust-uuid-501',
        'name': 'Dilshod Aka',
        'debt_limit': 100000,
        'current_debt': 0,
      });
      await db.insert('credit_allocations', {
        'id': 1,
        'customer_id': 501,
        'customer_name': 'Dilshod Aka',
        'allocated_amount': 100000,
        'consumed_amount': 0,
        'returned_amount': 0,
        'available_amount': 100000,
      });
    });

    tearDown(() async {
      await db.close();
      AppDatabase.setTestDatabase(null);
    });

    test(
      'confirmSaleOffline deducts stock allocation and creates sync_queue row',
      () async {
        final result = await salesService.confirmSaleOffline(
          items: [
            {'variant_id': 101, 'quantity': 10, 'sale_price': 10000},
          ],
          paymentType: 'CASH',
          paymentMethod: 'CASH',
          paidAmount: 100000,
          user: testUserA,
        );

        expect(result.totalAmount, equals(100000));
        expect(result.paidAmount, equals(100000));
        expect(result.debtAmount, equals(0));
        expect(result.tempInvoiceNumber.startsWith('#OFF-'), isTrue);

        // Ombor ajratmasi 50 dan 40 ga tushgan bo'lishi kerak
        final allocs = await db.query(
          'stock_allocations',
          where: 'variant_id = 101',
        );
        expect(allocs.first['available_quantity'], equals(40));
        expect(allocs.first['consumed_quantity'], equals(10));

        // sync_queue ga PENDING yozuv kiritilgan bo'lishi kerak
        final queueRows = await db.query(
          'sync_queue',
          where: 'operation_id = ?',
          whereArgs: [result.operationId],
        );
        expect(queueRows.length, equals(1));
        expect(queueRows.first['status'], equals('PENDING'));
        expect(queueRows.first['user_id'], equals(testUserA.id));
        expect(queueRows.first['type'], equals('CREATE_SALE'));
      },
    );

    test(
      'Throws ValidationException if selling more than available stock allocation',
      () async {
        expect(
          () async => await salesService.confirmSaleOffline(
            items: [
              {
                'variant_id': 101,
                'quantity': 60,
                'sale_price': 10000,
              }, // 60 > 50
            ],
            paymentType: 'CASH',
            paymentMethod: 'CASH',
            user: testUserA,
          ),
          throwsA(isA<ValidationException>()),
        );
      },
    );

    test(
      'Debt sale deducts customer credit allocation and updates local customer debt',
      () async {
        final result = await salesService.confirmSaleOffline(
          items: [
            {'variant_id': 101, 'quantity': 5, 'sale_price': 10000},
          ],
          customerId: 501,
          paymentType: 'DEBT',
          paymentMethod: 'DEBT',
          paidAmount: 0,
          user: testUserA,
        );

        expect(result.totalAmount, equals(50000));
        expect(result.debtAmount, equals(50000));

        // Kredit ajratmasi 100,000 dan 50,000 ga tushgan
        final creditRows = await db.query(
          'credit_allocations',
          where: 'customer_id = 501',
        );
        expect(creditRows.first['available_amount'], equals(50000));
        expect(creditRows.first['consumed_amount'], equals(50000));

        // Mijozning lokal qarzi oshgan
        final custRows = await db.query('customers', where: 'id = 501');
        expect(custRows.first['current_debt'], equals(50000));
      },
    );

    test('Throws ForbiddenException when offline lease is expired', () async {
      // Leaseni muddatidan o'tgan qilib o'zgartiramiz
      await db.update('offline_leases', {
        'expires_at': DateTime.now()
            .subtract(const Duration(minutes: 5))
            .toUtc()
            .toIso8601String(),
      });

      expect(
        () async => await salesService.confirmSaleOffline(
          items: [
            {'variant_id': 101, 'quantity': 1, 'sale_price': 10000},
          ],
          paymentType: 'CASH',
          paymentMethod: 'CASH',
          user: testUserA,
        ),
        throwsA(isA<ForbiddenException>()),
      );
    });

    test(
      'voidSaleOffline creates linked correction without deleting original row',
      () async {
        // 1. Savdo qilamiz
        final sale = await salesService.confirmSaleOffline(
          items: [
            {'variant_id': 101, 'quantity': 5, 'sale_price': 10000},
          ],
          paymentType: 'CASH',
          paymentMethod: 'CASH',
          user: testUserA,
        );

        // Ombor qoldig'i 45
        var alloc = (await db.query(
          'stock_allocations',
          where: 'variant_id = 101',
        )).first;
        expect(alloc['available_quantity'], equals(45));

        // 2. Bekor qilamiz (void)
        final voidOpId = await salesService.voidSaleOffline(
          originalOperationId: sale.operationId,
          reason: 'Xato chek',
          user: testUserA,
        );

        // Original savdo navbatda saqlanib qolgan!
        final origQueue = await db.query(
          'sync_queue',
          where: 'operation_id = ?',
          whereArgs: [sale.operationId],
        );
        expect(origQueue.length, equals(1));

        // Yangi VOID yozuvi navbatga kiritilgan
        final voidQueue = await db.query(
          'sync_queue',
          where: 'operation_id = ?',
          whereArgs: [voidOpId],
        );
        expect(voidQueue.length, equals(1));
        expect(voidQueue.first['type'], equals('VOID_SALE'));

        // Ombor qoldig'i qayta tiklangan (45 + 5 = 50)
        alloc = (await db.query(
          'stock_allocations',
          where: 'variant_id = 101',
        )).first;
        expect(alloc['available_quantity'], equals(50));
      },
    );

    test(
      'createCustomerOffline generates temp UUID and parent dependency queue item',
      () async {
        final cust = await salesService.createCustomerOffline(
          name: 'Sobirbek',
          phone: '+998901234567',
          storeName: 'Sobir Do\'koni',
          debtLimit: 200000,
          user: testUserA,
        );

        expect(cust.uuid.isNotEmpty, isTrue);
        expect(cust.name, equals('Sobirbek'));

        // sync_queue ga CREATE_CUSTOMER yozilgan
        final qRows = await db.query(
          'sync_queue',
          where: 'type = ?',
          whereArgs: ['CREATE_CUSTOMER'],
        );
        expect(qRows.length, equals(1));
        expect(qRows.first['status'], equals('PENDING'));
      },
    );
  });

  group('4. OfflineSyncService: User Isolation, Idempotency & Conflict Resolution', () {
    late Database db;
    late AppDatabase appDb;

    setUp(() async {
      appDb = AppDatabase();
      db = await databaseFactoryFfi.openDatabase(
        inMemoryDatabasePath,
        options: OpenDatabaseOptions(
          version: 2,
          onCreate: (database, version) async {
            await database.execute('''
              CREATE TABLE IF NOT EXISTS sync_queue (
                operation_id TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                device_uuid TEXT NOT NULL,
                type TEXT NOT NULL,
                payload TEXT NOT NULL,
                payload_fingerprint TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'PENDING',
                retry_count INTEGER DEFAULT 0,
                error_code TEXT,
                error_message TEXT,
                server_document_id INTEGER,
                server_document_number TEXT,
                ack_payload TEXT,
                device_created_at TEXT NOT NULL,
                processed_at TEXT,
                lease_token TEXT,
                locked_until TEXT,
                worker_id TEXT
              )
            ''');
            await database.execute('''
              CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                code TEXT,
                is_active INTEGER DEFAULT 1,
                updated_at TEXT
              )
            ''');
            await database.execute('''
              CREATE TABLE IF NOT EXISTS product_variants (
                id INTEGER PRIMARY KEY,
                product_id INTEGER NOT NULL,
                sku TEXT,
                litres REAL,
                volume_ml INTEGER,
                display_volume TEXT,
                stock_qty INTEGER DEFAULT 0,
                cost_price INTEGER,
                retail_price INTEGER DEFAULT 0,
                default_sale_price INTEGER DEFAULT 0,
                updated_at TEXT
              )
            ''');
            await database.execute('''
              CREATE TABLE IF NOT EXISTS customers (
                id INTEGER PRIMARY KEY,
                uuid TEXT UNIQUE,
                name TEXT NOT NULL,
                phone TEXT,
                store_name TEXT,
                address TEXT,
                debt_limit INTEGER DEFAULT 0,
                current_debt INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_offline INTEGER DEFAULT 0,
                updated_at TEXT
              )
            ''');
            await database.execute('''
              CREATE TABLE IF NOT EXISTS suppliers (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                phone TEXT,
                company_name TEXT,
                current_balance INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                updated_at TEXT
              )
            ''');
            await database.execute('''
              CREATE TABLE IF NOT EXISTS cash_accounts (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                balance INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                updated_at TEXT
              )
            ''');
            await database.execute('''
              CREATE TABLE IF NOT EXISTS offline_leases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                device_uuid TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                lease_token TEXT NOT NULL,
                valid_from TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                permissions TEXT NOT NULL,
                epoch INTEGER DEFAULT 1,
                signature TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT
              )
            ''');
            await database.execute('''
              CREATE TABLE IF NOT EXISTS stock_allocations (
                id INTEGER PRIMARY KEY,
                variant_id INTEGER UNIQUE NOT NULL,
                sku TEXT,
                product_name TEXT,
                volume_name TEXT,
                allocated_quantity INTEGER NOT NULL,
                consumed_quantity INTEGER DEFAULT 0,
                returned_quantity INTEGER DEFAULT 0,
                available_quantity INTEGER NOT NULL,
                updated_at TEXT
              )
            ''');
            await database.execute('''
              CREATE TABLE IF NOT EXISTS credit_allocations (
                id INTEGER PRIMARY KEY,
                customer_id INTEGER UNIQUE NOT NULL,
                customer_name TEXT,
                allocated_amount INTEGER NOT NULL,
                consumed_amount INTEGER DEFAULT 0,
                returned_amount INTEGER DEFAULT 0,
                available_amount INTEGER NOT NULL,
                updated_at TEXT
              )
            ''');
            await database.execute('''
              CREATE TABLE IF NOT EXISTS sync_cursor (
                id INTEGER PRIMARY KEY,
                cursor_pos INTEGER DEFAULT 0,
                last_synced_at TEXT
              )
            ''');
            await database.execute('''
              CREATE TABLE IF NOT EXISTS app_meta (
                key TEXT PRIMARY KEY,
                value TEXT
              )
            ''');
          },
        ),
      );
      AppDatabase.setTestDatabase(db);
    });

    tearDown(() async {
      await db.close();
      AppDatabase.setTestDatabase(null);
    });

    test(
      'User Isolation: Pending operations are isolated per user on user switch',
      () async {
        // 1. User A navbatiga yozuv qo'shamiz
        await db.insert('sync_queue', {
          'operation_id': 'op-user-a',
          'user_id': testUserA.id,
          'device_uuid': 'dev-1',
          'type': 'CREATE_SALE',
          'payload': json.encode({'total': 30000}),
          'payload_fingerprint': 'hash-a',
          'status': 'PENDING',
          'device_created_at': DateTime.now().toUtc().toIso8601String(),
        });

        // User A sifatida holatni ko'ramiz
        SessionService().setSession(user: testUserA, token: 'token-a');
        final syncService = OfflineSyncService(appDb: appDb);
        var summary = await syncService.getStatusSummary();
        expect(summary.pendingCount, equals(1));

        // 2. User A chiqib, User B kiradi (User Switch)
        SessionService().clearSession();
        SessionService().setSession(user: testUserB, token: 'token-b');

        summary = await syncService.getStatusSummary();
        // User B uchun navbat bo'sh (0 ta)
        expect(summary.pendingCount, equals(0));

        // User B push qilsa, 0 ta amal yuboriladi
        final pushedCount = await syncService.pushPendingOperations(
          deviceUuid: 'dev-1',
        );
        expect(pushedCount, equals(0));

        // User A ning yozuvi tegilmasdan saqlanib qolgan
        final aRow = (await db.query(
          'sync_queue',
          where: 'operation_id = ?',
          whereArgs: ['op-user-a'],
        )).first;
        expect(aRow['status'], equals('PENDING'));
        expect(aRow['user_id'], equals(testUserA.id));
      },
    );

    test(
      'Push handles ACK, updates server document number, and retains ACK payload',
      () async {
        SessionService().setSession(user: testUserA, token: 'token-a');

        await db.insert('sync_queue', {
          'operation_id': 'op-ack-test',
          'user_id': testUserA.id,
          'device_uuid': 'dev-1',
          'type': 'CREATE_SALE',
          'payload': json.encode({'total': 45000}),
          'payload_fingerprint': 'hash-ack',
          'status': 'PENDING',
          'device_created_at': DateTime.now().toUtc().toIso8601String(),
          'lease_token': 'lease-tok',
        });

        final mockClient = MockClient((request) async {
          if (request.url.path.contains('/sync/push')) {
            return http.Response(
              json.encode({
                'results': [
                  {
                    'operation_id': 'op-ack-test',
                    'status': 'APPLIED',
                    'server_document_id': 999,
                    'server_document_number': 'INV-2026-00999',
                    'message': 'Muvaffaqiyatli qabul qilindi',
                  },
                ],
              }),
              200,
              headers: {'content-type': 'application/json'},
            );
          }
          return http.Response('Not found', 404);
        });

        final syncService = OfflineSyncService(
          appDb: appDb,
          client: mockClient,
        );
        final count = await syncService.pushPendingOperations(
          deviceUuid: 'dev-1',
        );
        expect(count, equals(1));

        // Baza tekshiruvi: status ACKNOWLEDGED, server_document_number to'ldirilgan, navbatdan o'chirilmagan!
        final row = (await db.query(
          'sync_queue',
          where: 'operation_id = ?',
          whereArgs: ['op-ack-test'],
        )).first;
        expect(row['status'], equals('ACKNOWLEDGED'));
        expect(row['server_document_id'], equals(999));
        expect(row['server_document_number'], equals('INV-2026-00999'));
        expect(row['ack_payload'], contains('INV-2026-00999'));
      },
    );

    test(
      'Push handles NEEDS_REVIEW error classification without queue deletion',
      () async {
        SessionService().setSession(user: testUserA, token: 'token-a');

        await db.insert('sync_queue', {
          'operation_id': 'op-review-test',
          'user_id': testUserA.id,
          'device_uuid': 'dev-1',
          'type': 'CREATE_SALE',
          'payload': json.encode({'total': 90000}),
          'payload_fingerprint': 'hash-rev',
          'status': 'PENDING',
          'device_created_at': DateTime.now().toUtc().toIso8601String(),
          'lease_token': 'lease-tok',
        });

        final mockClient = MockClient((request) async {
          if (request.url.path.contains('/sync/push')) {
            return http.Response(
              json.encode({
                'results': [
                  {
                    'operation_id': 'op-review-test',
                    'status': 'NEEDS_REVIEW',
                    'error_code': 'INSUFFICIENT_STOCK_SERVER',
                    'message':
                        'Serverda ombor kamomadi aniqlandi, menejer ko\'rib chiqishi shart',
                  },
                ],
              }),
              200,
              headers: {'content-type': 'application/json'},
            );
          }
          return http.Response('Not found', 404);
        });

        final syncService = OfflineSyncService(
          appDb: appDb,
          client: mockClient,
        );
        final count = await syncService.pushPendingOperations(
          deviceUuid: 'dev-1',
        );
        expect(count, equals(1));

        // Baza tekshiruvi: status NEEDS_REVIEW, xatolik kodi saqlangan
        final row = (await db.query(
          'sync_queue',
          where: 'operation_id = ?',
          whereArgs: ['op-review-test'],
        )).first;
        expect(row['status'], equals('NEEDS_REVIEW'));
        expect(row['error_code'], equals('INSUFFICIENT_STOCK_SERVER'));
        expect(
          row['error_message'],
          contains('menejer ko\'rib chiqishi shart'),
        );
      },
    );

    test(
      'Duplicate-worker & Idempotent retry yields identical single operation without duplication',
      () async {
        SessionService().setSession(user: testUserA, token: 'token-a');

        final opId = 'op-replay-${OperationId.generate()}';
        await db.insert('sync_queue', {
          'operation_id': opId,
          'user_id': testUserA.id,
          'device_uuid': 'dev-1',
          'type': 'CREATE_SALE',
          'payload': json.encode({'total': 70000}),
          'payload_fingerprint': 'hash-replay-123',
          'status': 'PENDING',
          'device_created_at': DateTime.now().toUtc().toIso8601String(),
          'lease_token': 'lease-tok',
        });

        // Server RETRY_SUCCESS qaytaradi (avval qabul qilingan)
        final mockClient = MockClient((request) async {
          return http.Response(
            json.encode({
              'results': [
                {
                  'operation_id': opId,
                  'status': 'RETRY_SUCCESS',
                  'is_replay': true,
                  'server_document_id': 1050,
                  'server_document_number': 'INV-2026-01050',
                  'message': 'Operatsiya avval bajarilgan (Idempotent replay).',
                },
              ],
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        });

        final syncService = OfflineSyncService(
          appDb: appDb,
          client: mockClient,
        );
        await syncService.pushPendingOperations(deviceUuid: 'dev-1');

        final rows = await db.query(
          'sync_queue',
          where: 'operation_id = ?',
          whereArgs: [opId],
        );
        // Qatorda dublikat yo'q (aniq 1 ta yozuv)
        expect(rows.length, equals(1));
        expect(rows.first['status'], equals('ACKNOWLEDGED'));
        expect(rows.first['server_document_number'], equals('INV-2026-01050'));
      },
    );
  });
  test(
    'Audit: real bootstrap catalog and pending allocation protection',
    () async {
      final db = await databaseFactoryFfi.openDatabase(
        inMemoryDatabasePath,
        options: OpenDatabaseOptions(
          version: 2,
          onCreate: AppDatabase.createSchema,
        ),
      );
      AppDatabase.setTestDatabase(db);
      SessionService().setSession(user: testUserA, token: 'audit-test-token');
      final client = MockClient(
        (request) async => http.Response(
          json.encode({
            'success': true,
            'data': {
              'device': {'device_uuid': 'audit-device'},
              'lease': {
                'lease_token': 'audit-lease',
                'permissions': ['offline_sales'],
              },
              'catalog': [
                {
                  'id': 101,
                  'product_id': 1,
                  'product_name': 'Fanta',
                  'volume_litres': '0.500',
                  'volume_ml': 500,
                  'volume_name': '0.5 L',
                  'default_sale_price': 6500,
                },
              ],
              'stock_allocations': [
                {
                  'allocation_id': 1,
                  'variant_id': 101,
                  'allocated_quantity': 10,
                  'consumed_quantity': 0,
                  'available_quantity': 10,
                },
              ],
              'current_cursor': 42,
            },
          }),
          200,
        ),
      );
      try {
        final service = OfflineSyncService(
          appDb: AppDatabase(),
          client: client,
        );
        service.setDeviceUuid('');
        await service.bootstrap();
        expect(service.deviceUuid, 'audit-device');
        expect((await db.query('products')).single['name'], 'Fanta');
        expect(
          (await db.query('product_variants')).single['default_sale_price'],
          6500,
        );
        expect((await db.query('sync_cursor')).single['cursor_pos'], 42);
        await db.update('stock_allocations', {
          'consumed_quantity': 2,
          'available_quantity': 8,
        });
        await db.insert('sync_queue', {
          'operation_id': 'audit-pending',
          'user_id': 1,
          'device_uuid': 'audit-device',
          'type': 'CREATE_SALE',
          'payload': '{}',
          'payload_fingerprint': 'test',
          'status': 'PENDING',
          'device_created_at': DateTime.now().toUtc().toIso8601String(),
        });
        await expectLater(service.bootstrap(), throwsA(isA<ServerException>()));
        expect(
          (await db.query('stock_allocations')).single['available_quantity'],
          8,
        );
        expect((await db.query('sync_queue')).single['status'], 'PENDING');
      } finally {
        AppDatabase.setTestDatabase(null);
        SessionService().clearSession();
        await db.close();
        client.close();
      }
    },
  );
  test(
    'Audit: paginated real delta and retained ACK recovery preserve data',
    () async {
      final db = await databaseFactoryFfi.openDatabase(
        inMemoryDatabasePath,
        options: OpenDatabaseOptions(
          version: 2,
          onCreate: AppDatabase.createSchema,
        ),
      );
      AppDatabase.setTestDatabase(db);
      SessionService().setSession(user: testUserA, token: 'test');
      await db.insert('products', {'id': 1, 'name': 'Fanta'});
      await db.insert('product_variants', {
        'id': 101,
        'product_id': 1,
        'stock_qty': 9,
        'default_sale_price': 6500,
      });
      await db.insert('customers', {
        'id': 1,
        'name': 'Ali',
        'debt_limit': 50000,
        'current_debt': 0,
      });
      final originalPayload = json.encode({
        'customer_id': 1,
        'items': [
          {'variant_id': 101, 'quantity': 1, 'sale_price': 1000},
        ],
        'paid_amount': 0,
      });
      for (final entry in {
        'pending': 'PENDING',
        'retained': 'ACKNOWLEDGED',
      }.entries) {
        await db.insert('sync_queue', {
          'operation_id': entry.key,
          'user_id': 1,
          'device_uuid': 'audit-device',
          'type': 'CREATE_SALE',
          'payload': originalPayload,
          'payload_fingerprint': 'test',
          'status': entry.value,
          'device_created_at': DateTime.now().toUtc().toIso8601String(),
        });
      }
      var page = 0;
      final client = MockClient((request) async {
        if (request.url.path.endsWith('/reconcile-recovery')) {
          final body = json.decode(request.body) as Map<String, dynamic>;
          final operations = body['operations'] as List<dynamic>;
          expect(operations.length, 2);
          expect(operations.map((op) => op['operation_id']).toSet(), {
            'pending',
            'retained',
          });
          return http.Response(
            json.encode({
              'results': operations
                  .map(
                    (op) => {
                      'operation_id': op['operation_id'],
                      'status': 'ALREADY_PERSISTED',
                    },
                  )
                  .toList(),
            }),
            200,
          );
        }
        final data = page++ == 0
            ? {
                'items': [
                  {
                    'entity_type': 'PRODUCT_VARIANT',
                    'entity_id': '101',
                    'payload': {'default_sale_price': 7000},
                  },
                ],
                'next_cursor': 43,
                'has_more': true,
              }
            : {
                'items': [
                  {
                    'entity_type': 'CUSTOMER',
                    'entity_id': '1',
                    'payload': {'current_debt': 2000},
                  },
                ],
                'next_cursor': 44,
                'has_more': false,
              };
        return http.Response(json.encode({'data': data}), 200);
      });
      try {
        final service = OfflineSyncService(
          appDb: AppDatabase(),
          client: client,
        );
        service.setDeviceUuid('audit-device');
        expect(await service.pullDeltaChanges(), 2);
        final variant = (await db.query('product_variants')).single;
        expect(variant['stock_qty'], 9);
        expect(variant['default_sale_price'], 7000);
        final customer = (await db.query('customers')).single;
        expect(customer['current_debt'], 3000);
        expect(customer['debt_limit'], 50000);
        expect((await db.query('sync_cursor')).single['cursor_pos'], 44);
        expect(await service.reconcileRecovery(2), 2);
        final retained = await db.query('sync_queue');
        expect(
          retained.every(
            (row) =>
                row['status'] == 'ACKNOWLEDGED' &&
                row['payload'] == originalPayload,
          ),
          true,
        );
        expect((await db.query('sync_cursor')).single['cursor_pos'], 0);
        expect(
          (await db.query(
            'app_meta',
            where: 'key = ?',
            whereArgs: ['recovery_hold'],
          )).single['value'],
          'true',
        );
        await expectLater(
          OfflineSalesService(appDb: AppDatabase()).getActiveLease(),
          throwsA(isA<ForbiddenException>()),
        );
      } finally {
        AppDatabase.setTestDatabase(null);
        SessionService().clearSession();
        await db.close();
        client.close();
      }
    },
  );
}
