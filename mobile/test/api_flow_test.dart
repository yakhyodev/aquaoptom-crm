import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:mobile/models/user_model.dart';
import 'package:mobile/services/api_exceptions.dart';
import 'package:mobile/services/api_service.dart';
import 'package:mobile/services/event_invalidation_service.dart';
import 'package:mobile/services/session_service.dart';
import 'package:mobile/utils/operation_id.dart';

void main() {
  setUp(() {
    SessionService().clearSession();
    EventInvalidationService().resetCursor();
  });

  tearDown(() {
    SessionService().clearSession();
    EventInvalidationService().resetCursor();
  });

  group('AquaOptom API Contract & Flow Tests', () {
    test('Full business flow: Login -> Inward -> Sale -> Debt/Payment -> Stock -> Event Invalidation',
        () async {
      // Mock database state tracking
      bool isLoggedIn = false;
      String currentToken = '';
      int currentStock = 0;
      int customerBalance = 0; // Negative means customer owes us

      final client = MockClient((request) async {
        final path = request.url.path;

        // Verify headers for authenticated endpoints
        if (path != '/api/auth/login') {
          final authHeader = request.headers['Authorization'];
          if (authHeader != 'Bearer $currentToken') {
            return http.Response(
              json.encode({'message': 'Unauthenticated.'}),
              401,
              headers: {'content-type': 'application/json'},
            );
          }
        }

        // 1. LOGIN
        if (path == '/api/auth/login' && request.method == 'POST') {
          final body = json.decode(request.body) as Map<String, dynamic>;
          if (body['email'] == 'owner@aquaoptom.uz' && body['password'] == 'Secret123!') {
            isLoggedIn = true;
            currentToken = 'sanctum-mock-token-998877';
            return http.Response(
              json.encode({
                'token': currentToken,
                'user': {
                  'id': 1,
                  'name': 'Akrom Karimov',
                  'email': 'owner@aquaoptom.uz',
                  'role': 'owner',
                  'permissions': [
                    'view_dashboard',
                    'manage_sales',
                    'view_cost_price',
                    'manage_inventory',
                    'view_debt',
                    'view_reports',
                  ],
                },
              }),
              200,
              headers: {'content-type': 'application/json'},
            );
          } else {
            return http.Response(
              json.encode({'message': 'Telefon raqam yoki parol noto\'g\'ri'}),
              401,
              headers: {'content-type': 'application/json'},
            );
          }
        }

        // 2. INWARD (Kirim)
        if (path == '/api/inward' && request.method == 'POST') {
          final body = json.decode(request.body) as Map<String, dynamic>;
          expect(body['product_name'], 'Aqualux Gazsiz');
          expect(body['litres'], 1.5);
          expect(body['quantity'], 100);
          expect(body['cost_price'], 3500);

          currentStock += (body['quantity'] as num).toInt();

          return http.Response(
            json.encode({
              'success': true,
              'message': 'Kirim muvaffaqiyatli qabul qilindi',
              'data': {
                'batch_id': 101,
                'product_id': 1,
                'variant_id': 5,
                'quantity': 100,
                'unit_cost': 3500,
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        // 3. SALES (Sotuv)
        if (path == '/api/sales' && request.method == 'POST') {
          final body = json.decode(request.body) as Map<String, dynamic>;
          final items = body['items'] as List<dynamic>;
          expect(items.length, 1);
          final item = items[0] as Map<String, dynamic>;
          expect(item['variant_id'], 5);
          expect(item['quantity'], 20);
          expect(item['sale_price'], 5000);

          // Verify stable UUID operation_id
          final operationId = body['operation_id'] as String?;
          expect(operationId, isNotNull);
          expect(
            RegExp(r'^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$')
                .hasMatch(operationId!),
            isTrue,
          );

          // Partial payment: total 100,000, paid 60,000, debt 40,000
          expect(body['payment_type'], 'PARTIAL');
          expect(body['paid_amount'], 60000);
          expect(body['customer_id'], 12);

          currentStock -= (item['quantity'] as num).toInt(); // 100 - 20 = 80
          customerBalance -= 40000; // Customer owes 40,000

          return http.Response(
            json.encode({
              'success': true,
              'message': 'Savdo muvaffaqiyatli saqlandi',
              'data': {
                'id': 201,
                'sale_number': 'ORD-2026-0001',
                'customer_id': 12,
                'customer_name': 'Sherzod aka (Do\'kon)',
                'total_amount': 100000,
                'paid_amount': 60000,
                'debt_amount': 40000,
                'payment_type': 'PARTIAL',
                'payment_method': 'CASH',
                'operation_id': operationId,
                'created_at': '2026-10-04T10:00:00Z',
                'items': [
                  {
                    'id': 501,
                    'variant_id': 5,
                    'product_name': 'Aqualux Gazsiz 1.5L',
                    'quantity': 20,
                    'unit_price': 5000,
                    'total_price': 100000,
                  }
                ],
                'receipt': {
                  'store_name': 'AquaOptom Wholesale',
                  'sale_number': 'ORD-2026-0001',
                  'created_at': '2026-10-04 15:00:00',
                  'customer_name': 'Sherzod aka (Do\'kon)',
                  'cashier_name': 'Akrom Karimov',
                  'total_amount': 100000,
                  'paid_amount': 60000,
                  'debt_amount': 40000,
                  'payment_method': 'CASH',
                  'items': [
                    {
                      'name': 'Aqualux Gazsiz 1.5L',
                      'quantity': 20,
                      'unit_price': 5000,
                      'total_price': 100000,
                    }
                  ],
                },
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        // 4. DEBT / PAYMENT (Qarz yopish)
        if (path == '/api/payments' && request.method == 'POST') {
          final body = json.decode(request.body) as Map<String, dynamic>;
          expect(body['type'], 'customer');
          expect(body['party_id'], 12);
          expect(body['amount'], 40000);

          customerBalance += (body['amount'] as num).toInt(); // 0 balance restored

          return http.Response(
            json.encode({
              'success': true,
              'message': 'To\'lov muvaffaqiyatli qabul qilindi',
              'data': {
                'id': 301,
                'customer_id': 12,
                'amount': 40000,
                'payment_method': 'CASH',
                'remaining_debt': 0,
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        // 5. PRODUCTS & STOCK CHECK (Qoldiq tekshirish)
        if (path == '/api/products' && request.method == 'GET') {
          return http.Response(
            json.encode({
              'data': [
                {
                  'id': 1,
                  'name': 'Aqualux Gazsiz',
                  'variants': [
                    {
                      'id': 5,
                      'product_id': 1,
                      'volume_litres': 1.5,
                      'sale_price': 5000,
                      'cost_price': 3500, // Owner sees cost price
                      'stock_quantity': currentStock,
                      'is_active': true,
                    }
                  ],
                }
              ],
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        // 6. EVENT INVALIDATION ADAPTER (Cursor catch-up)
        if (path == '/api/events/invalidation' && request.method == 'GET') {
          final cursorParam = request.url.queryParameters['cursor'] ?? '0';
          final cursor = int.tryParse(cursorParam) ?? 0;

          return http.Response(
            json.encode({
              'cursor': cursor + 5,
              'invalidated_resources': ['sales', 'stock', 'payments'],
              'events_count': 3,
              'events': [
                {
                  'id': cursor + 1,
                  'type': 'inward.created',
                  'resource': 'stock',
                },
                {
                  'id': cursor + 2,
                  'type': 'sale.created',
                  'resource': 'sales',
                },
                {
                  'id': cursor + 3,
                  'type': 'payment.created',
                  'resource': 'payments',
                },
              ],
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        return http.Response('Not Found', 404);
      });

      final api = ApiService(client: client);

      // STEP 1: LOGIN
      final user = await api.login(
        email: 'owner@aquaoptom.uz',
        password: 'Secret123!',
      );
      expect(user.email, 'owner@aquaoptom.uz');
      expect(user.isOwner, isTrue);
      expect(SessionService().isAuthenticated, isTrue);
      expect(SessionService().token, 'sanctum-mock-token-998877');
      expect(isLoggedIn, isTrue);

      // STEP 2: INWARD (Kirim 100 dona)
      final inwardRes = await api.createInward(
        productName: 'Aqualux Gazsiz',
        litres: 1.5,
        volumeMl: 1500,
        quantity: 100,
        packageName: 'dona',
        costPrice: 3500,
        supplierName: 'Suv Sanoat MCHJ',
      );
      expect(inwardRes['success'], isTrue);
      expect(currentStock, 100);

      // STEP 3: SALE (20 dona sotuv, 40,000 qarz)
      final operationId = OperationId.generate();
      final sale = await api.createSale(
        customerId: 12,
        customerName: 'Sherzod aka (Do\'kon)',
        items: [
          {'variant_id': 5, 'quantity': 20, 'sale_price': 5000}
        ],
        paymentType: 'PARTIAL',
        paymentMethod: 'CASH',
        paidAmount: 60000,
        cashAccountId: 1,
        operationId: operationId,
      );
      expect(sale.totalAmount, 100000);
      expect(sale.paidAmount, 60000);
      expect(sale.debtAmount, 40000);
      expect(sale.items.first.quantity, 20);
      expect(currentStock, 80);
      expect(customerBalance, -40000);

      // STEP 4: DEBT PAYMENT (40,000 qarz to'landi)
      final paymentRes = await api.createPayment(
        type: 'customer',
        partyId: 12,
        amount: 40000,
        paymentMethod: 'CASH',
        cashAccountId: 1,
        operationId: OperationId.generate(),
      );
      expect(paymentRes['success'], isTrue);
      expect(customerBalance, 0);

      // STEP 5: VERIFY STOCK & EXACT INTEGER VALUES
      final products = await api.getProducts();
      expect(products.length, 1);
      final variant = products.first.variants.first;
      expect(variant.stockQuantity, 80);
      expect(variant.costPrice, 3500);
      expect(variant.salePrice, 5000);

      // STEP 6: EVENT / CURSOR INVALIDATION REPOSITORY ADAPTER
      final invalidationService = EventInvalidationService();
      expect(invalidationService.lastCursor, 0);

      final pollResult = await invalidationService.pollUpdates(client: client);
      expect(pollResult.cursor, 5);
      expect(pollResult.invalidatedResources, contains('sales'));
      expect(pollResult.invalidatedResources, contains('stock'));
      expect(pollResult.invalidatedResources, contains('payments'));
      expect(invalidationService.lastCursor, 5);
    });

    test('Cost price is masked for unprivileged roles without leaking data', () async {
      final client = MockClient((request) async {
        if (request.url.path == '/api/products') {
          return http.Response(
            json.encode({
              'data': [
                {
                  'id': 1,
                  'name': 'Aqualux Gazsiz',
                  'variants': [
                    {
                      'id': 5,
                      'product_id': 1,
                      'volume_litres': 1.5,
                      'sale_price': 5000,
                      'cost_price': null, // MASKED BY BACKEND FOR CASHIER
                      'stock_quantity': 80,
                      'is_active': true,
                    }
                  ],
                }
              ],
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.Response('Not Found', 404);
      });

      // Set Cashier user (canViewCost is false)
      final cashierUser = UserModel(
        id: 2,
        name: 'Olim Kassir',
        email: 'kassir@aquaoptom.uz',
        role: 'cashier',
        permissions: ['manage_sales'],
      );
      SessionService().setSession(user: cashierUser, token: 'cashier-token');

      final api = ApiService(client: client);
      final products = await api.getProducts();
      final variant = products.first.variants.first;

      expect(cashierUser.canViewCost, isFalse);
      expect(variant.costPrice, isNull);
      expect(variant.stockQuantity, 80);
      expect(variant.salePrice, 5000);
    });

    test('Typed API Exceptions handle 401, 403, and 422 cleanly without exposing tokens',
        () async {
      final client = MockClient((request) async {
        if (request.url.path == '/api/auth/me') {
          return http.Response(
            json.encode({'message': 'Unauthenticated session'}),
            401,
            headers: {'content-type': 'application/json'},
          );
        }
        if (request.url.path == '/api/sales') {
          return http.Response(
            json.encode({
              'message': 'The given data was invalid.',
              'errors': {
                'items': ['Kamida bitta tovar tanlanishi shart!'],
                'operation_id': ['UUID formati talab qilinadi.'],
              },
            }),
            422,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.Response(
          json.encode({'message': 'Ruxsat etilmagan amal'}),
          403,
          headers: {'content-type': 'application/json'},
        );
      });

      final api = ApiService(client: client);
      SessionService().setSession(
        user: UserModel(id: 1, name: 'Test', email: 'test@aquaoptom.uz', role: 'cashier'),
        token: 'secret-token-to-verify',
      );

      // Test 401 Unauthorized clears session
      await expectLater(
        api.me(),
        throwsA(isA<UnauthorizedException>()),
      );
      expect(SessionService().isAuthenticated, isFalse);

      // Test 422 ValidationException parsed cleanly
      try {
        await api.createSale(items: []);
        fail('Should have thrown ValidationException');
      } on ValidationException catch (e) {
        expect(e.errors.containsKey('items'), isTrue);
        expect(e.errors['items'], contains('Kamida bitta tovar tanlanishi shart!'));
        expect(e.errors.containsKey('operation_id'), isTrue);
        // Ensure token is not present in exception message
        expect(e.message.contains('secret-token-to-verify'), isFalse);
      }

      // Test 403 ForbiddenException
      await expectLater(
        api.getDashboard(),
        throwsA(isA<ForbiddenException>()),
      );
    });
  });
}
