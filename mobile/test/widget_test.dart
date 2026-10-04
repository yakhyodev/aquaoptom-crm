import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:mobile/main.dart';
import 'package:mobile/models/user_model.dart';
import 'package:mobile/services/api_service.dart';
import 'package:mobile/services/session_service.dart';

ApiService _createMockApiService() {
  final mockClient = MockClient((request) async {
    final path = request.url.path;

    if (path == '/api/dashboard') {
      return http.Response(
        json.encode({
          'data': {
            'flow': {
              'total_sales': 100000,
              'cash_collected': 80000,
              'new_debt': 20000,
              'gross_profit': 30000,
              'total_expense': 5000,
              'net_profit': 25000,
            },
            'balances': {
              'cash_in_hand': 50000,
              'bank_balance': 100000,
              'card_balance': 20000,
              'total_cash': 170000,
              'customer_debts': 30000,
              'supplier_payables': 15000,
              'total_stock_cost': 200000,
              'total_potential_revenue': 300000,
            },
            'warnings': {
              'low_stock_count': 2,
              'high_debt_customers_count': 1,
              'open_cash_sessions_count': 1,
            },
            'recent_activity': [],
          }
        }),
        200,
        headers: {'content-type': 'application/json'},
      );
    }

    if (path == '/api/products' ||
        path == '/api/customers' ||
        path == '/api/suppliers' ||
        path == '/api/cash-accounts' ||
        path == '/api/sales/history') {
      return http.Response(
        json.encode({'data': []}),
        200,
        headers: {'content-type': 'application/json'},
      );
    }

    if (path == '/api/reports') {
      return http.Response(
        json.encode({
          'data': {
            'sales': {'total_sales': 0, 'count': 0},
            'cash': {'total_inflow': 0, 'total_outflow': 0, 'net': 0},
          }
        }),
        200,
        headers: {'content-type': 'application/json'},
      );
    }

    return http.Response(
      json.encode({'message': 'OK'}),
      200,
      headers: {'content-type': 'application/json'},
    );
  });

  return ApiService(client: mockClient);
}

void main() {
  setUp(() {
    SessionService().clearSession();
  });

  tearDown(() {
    SessionService().clearSession();
  });

  group('AquaOptomApp UI Smoke Tests', () {
    testWidgets('Unauthenticated user sees LoginScreen and can open Server Settings',
        (WidgetTester tester) async {
      final api = _createMockApiService();
      await tester.pumpWidget(AquaOptomApp(apiService: api));
      await tester.pumpAndSettle();

      // Verify LoginScreen UI elements
      expect(find.text('AquaOptom'), findsOneWidget);
      expect(find.text('Tizimga kirish'), findsOneWidget);
      expect(find.byType(TextField), findsNWidgets(2)); // Email/phone and password fields

      // Find and tap Server Settings button
      final serverButton = find.textContaining('Server:');
      expect(serverButton, findsOneWidget);

      await tester.ensureVisible(serverButton);
      await tester.tap(serverButton);
      await tester.pumpAndSettle();

      // Check modal dialog
      expect(find.text('API Server Manzili'), findsOneWidget);
      expect(find.text('Standartga qaytarish'), findsOneWidget);
      expect(find.text('Saqlash'), findsOneWidget);

      // Tap save to close modal
      await tester.tap(find.text('Saqlash'));
      await tester.pumpAndSettle();
      expect(find.text('API Server Manzili'), findsNothing);
    });

    testWidgets('Owner role renders MainNavigationScreen with all tabs',
        (WidgetTester tester) async {
      final ownerUser = UserModel(
        id: 1,
        name: 'Aziz Rahimov',
        email: 'owner@aquaoptom.uz',
        role: 'OWNER',
        permissions: [
          'view_dashboard',
          'manage_sales',
          'view_cost_price',
          'manage_inventory',
          'view_debt',
          'view_reports',
          'manage_users',
        ],
      );

      // Simulate logged in session
      SessionService().setSession(user: ownerUser, token: 'fake-jwt-token-for-test');

      final api = _createMockApiService();
      await tester.pumpWidget(AquaOptomApp(apiService: api));
      await tester.pumpAndSettle();

      // Verify Main Navigation Screen tabs for Owner
      expect(find.text('Dashboard'), findsNWidgets(2)); // AppBar + BottomNavBar
      expect(find.text('POS'), findsOneWidget);
      expect(find.text('Katalog'), findsOneWidget);
      expect(find.text('Qarzlar'), findsOneWidget);
      expect(find.text('Tarix'), findsOneWidget);

      // Switch to POS tab
      await tester.tap(find.text('POS'));
      await tester.pumpAndSettle();
      expect(find.text('Savat hozircha bo\'sh'), findsOneWidget);
      expect(find.text('Tezkor Xaridor'), findsOneWidget);

      // Switch to Katalog tab
      await tester.tap(find.text('Katalog'));
      await tester.pumpAndSettle();
      expect(find.byType(TextField), findsOneWidget); // Search box
      expect(find.byIcon(Icons.search), findsWidgets);

      // Switch to Qarzlar tab
      await tester.tap(find.text('Qarzlar'));
      await tester.pumpAndSettle();
      expect(find.text('Mijozlar Qarzi'), findsOneWidget);
      expect(find.text('Ta\'minotchilarga Qarz'), findsOneWidget);

      // Switch to Tarix tab
      await tester.tap(find.text('Tarix'));
      await tester.pumpAndSettle();
      expect(find.byIcon(Icons.receipt_long), findsWidgets);
    });

    testWidgets('Cashier role renders restricted tabs without dashboard',
        (WidgetTester tester) async {
      final cashierUser = UserModel(
        id: 2,
        name: 'Olim Kassir',
        email: 'kassir@aquaoptom.uz',
        role: 'CASHIER',
        permissions: [
          'manage_sales',
          'view_debt',
        ],
      );

      SessionService().setSession(user: cashierUser, token: 'cashier-token');

      final api = _createMockApiService();
      await tester.pumpWidget(AquaOptomApp(apiService: api));
      await tester.pumpAndSettle();

      // Cashier should have POS, Katalog, Qarzlar, Tarix, but NOT Dashboard
      expect(find.text('Dashboard'), findsNothing);
      expect(find.text('POS'), findsOneWidget);
      expect(find.text('Katalog'), findsOneWidget);
      expect(find.text('Qarzlar'), findsOneWidget);
      expect(find.text('Tarix'), findsOneWidget);
    });
  });
}
