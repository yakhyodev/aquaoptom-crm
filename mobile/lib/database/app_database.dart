import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:path/path.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

class AppDatabase {
  static final AppDatabase _instance = AppDatabase._internal();
  factory AppDatabase() => _instance;
  AppDatabase._internal();

  Database? _db;

  /// Testlar yoki maxsus in-memory / FFI bazani ulash uchun
  static Database? _overrideDb;
  static void setTestDatabase(Database? db) {
    _overrideDb = db;
  }

  Future<Database> get database async {
    if (_overrideDb != null) return _overrideDb!;
    if (_db != null) return _db!;
    _db = await _initDatabase();
    return _db!;
  }

  static bool _ffiInitialized = false;

  static void initializeFfiIfNeeded() {
    if (!_ffiInitialized) {
      if (Platform.isWindows || Platform.isLinux || Platform.isMacOS || kIsWeb) {
        sqfliteFfiInit();
        databaseFactory = databaseFactoryFfi;
      }
      _ffiInitialized = true;
    }
  }

  Future<Database> _initDatabase({String dbName = 'aquaoptom_offline.db'}) async {
    initializeFfiIfNeeded();

    String path;
    if (Platform.isWindows || Platform.isLinux || Platform.isMacOS) {
      final dbPath = await databaseFactory.getDatabasesPath();
      path = join(dbPath, dbName);
    } else {
      path = join(await getDatabasesPath(), dbName);
    }

    return await openDatabase(
      path,
      version: 2,
      onConfigure: (db) async {
        await db.execute('PRAGMA foreign_keys = ON');
      },
      onCreate: (db, version) async {
        await _createTablesV1(db);
        if (version >= 2) {
          await _upgradeV1ToV2(db);
        }
      },
      onUpgrade: (db, oldVersion, newVersion) async {
        if (oldVersion < 2) {
          await _upgradeV1ToV2(db);
        }
      },
    );
  }

  static Future<void> _createTablesV1(DatabaseExecutor db) async {
    // 1. Tovarlar
    await db.execute('''
      CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        code TEXT,
        is_active INTEGER DEFAULT 1,
        updated_at TEXT
      )
    ''');

    // 2. Tovar variantlari
    await db.execute('''
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

    // 3. Mijozlar
    await db.execute('''
      CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY,
        uuid TEXT UNIQUE,
        name TEXT NOT NULL,
        phone TEXT,
        store_name TEXT,
        address TEXT,
        current_debt INTEGER DEFAULT 0,
        debt_limit INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_offline INTEGER DEFAULT 0,
        updated_at TEXT
      )
    ''');

    // 4. Ta'minotchilar
    await db.execute('''
      CREATE TABLE IF NOT EXISTS suppliers (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        company_name TEXT,
        phone TEXT,
        address TEXT,
        current_balance INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        updated_at TEXT
      )
    ''');

    // 5. Kassa hisoblari
    await db.execute('''
      CREATE TABLE IF NOT EXISTS cash_accounts (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        type TEXT,
        balance INTEGER DEFAULT 0,
        is_default INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        updated_at TEXT
      )
    ''');

    // 6. Offline ruxsatnomalar (Leases)
    await db.execute('''
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
        is_active INTEGER DEFAULT 1
      )
    ''');

    // 7. Tovar ajratmalari (Stock Allocations)
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

    // 8. Kredit ajratmalari (Credit Allocations)
    await db.execute('''
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

    // 9. Sync Navbati (Sync Queue) - Migrationda HECH QACHON O'CHIRILMAYDI!
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

    // 10. Sync kursor va meta
    await db.execute('''
      CREATE TABLE IF NOT EXISTS sync_cursor (
        id INTEGER PRIMARY KEY,
        cursor_pos INTEGER DEFAULT 0,
        last_synced_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE IF NOT EXISTS app_meta (
        key TEXT PRIMARY KEY,
        value TEXT
      )
    ''');
  }

  /// Version 2 ga yangilash (navbatdagi ma'lumotlarni saqlagan holda)
  static Future<void> _upgradeV1ToV2(DatabaseExecutor db) async {
    // Indekslar qo'shish (agar avval mavjud bo'lmasa)
    await db.execute('CREATE INDEX IF NOT EXISTS idx_sync_queue_user_status ON sync_queue (user_id, status)');
    await db.execute('CREATE INDEX IF NOT EXISTS idx_sync_queue_created ON sync_queue (device_created_at)');
    await db.execute('CREATE INDEX IF NOT EXISTS idx_stock_alloc_variant ON stock_allocations (variant_id)');
    await db.execute('CREATE INDEX IF NOT EXISTS idx_credit_alloc_cust ON credit_allocations (customer_id)');
  }

  /// Bazani tozalash (faqat testlar uchun)
  Future<void> clearAll() async {
    final db = await database;
    await db.delete('products');
    await db.delete('product_variants');
    await db.delete('customers');
    await db.delete('suppliers');
    await db.delete('cash_accounts');
    await db.delete('offline_leases');
    await db.delete('stock_allocations');
    await db.delete('credit_allocations');
    await db.delete('sync_queue');
    await db.delete('sync_cursor');
    await db.delete('app_meta');
  }

  Future<void> close() async {
    if (_db != null) {
      await _db!.close();
      _db = null;
    }
  }
}
