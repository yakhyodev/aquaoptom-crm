import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
import '../models/cash_account_model.dart';
import '../models/customer_model.dart';
import '../models/dashboard_model.dart';
import '../models/product_model.dart';
import '../models/report_model.dart';
import '../models/sale_model.dart';
import '../models/supplier_model.dart';
import '../models/user_model.dart';
import 'api_exceptions.dart';
import 'session_service.dart';

class ApiService {
  final http.Client _client;

  ApiService({http.Client? client}) : _client = client ?? http.Client();

  String get baseUrl => AppConfig.apiBaseUrl;

  Map<String, String> _buildHeaders() {
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };

    final token = SessionService().token;
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }

    return headers;
  }

  /// Xatoliklarni qayta ishlash va typed exceptionga o'girish
  Never _handleErrorResponse(http.Response response) {
    Map<String, dynamic> body = {};
    try {
      body = json.decode(response.body) as Map<String, dynamic>;
    } catch (_) {}

    final message =
        body['message'] as String? ??
        body['error'] as String? ??
        'Xatolik yuz berdi (${response.statusCode})';

    switch (response.statusCode) {
      case 401:
        SessionService().clearSession();
        throw UnauthorizedException(message, body);
      case 403:
        throw ForbiddenException(message, body);
      case 422:
        final rawErrors = body['errors'] as Map<String, dynamic>? ?? {};
        final parsedErrors = rawErrors.map((key, value) {
          if (value is List) {
            return MapEntry(key, value.map((e) => e.toString()).toList());
          }
          return MapEntry(key, [value.toString()]);
        });
        throw ValidationException(message, errors: parsedErrors, details: body);
      case 500:
      case 502:
      case 503:
        throw ServerException(message, response.statusCode, body);
      default:
        throw ServerException(message, response.statusCode, body);
    }
  }

  Future<http.Response> _sendRequest(
    Future<http.Response> Function() requestFn,
  ) async {
    try {
      final res = await requestFn().timeout(const Duration(seconds: 15));
      if (res.statusCode >= 200 && res.statusCode < 300) {
        return res;
      }
      _handleErrorResponse(res);
    } on ApiException {
      rethrow;
    } on SocketException catch (e) {
      throw NetworkException("Serverga ulanib bo'lmadi: ${e.message}");
    } on TimeoutException {
      throw const NetworkException(
        "Server javob berish vaqti tugadi (Timeout).",
      );
    } on http.ClientException catch (e) {
      throw NetworkException("Tarmoq xatosi: ${e.message}");
    } catch (e) {
      throw NetworkException("Kutilmagan xatolik: $e");
    }
  }

  // ==========================================
  // AUTHENTICATION
  // ==========================================

  Future<UserModel> login({
    required String email,
    required String password,
    String deviceName = 'AquaOptom Android Mobile',
  }) async {
    final response = await _sendRequest(
      () => _client.post(
        Uri.parse('$baseUrl/auth/login'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: json.encode({
          'email': email.trim(),
          'password': password,
          'device_name': deviceName,
        }),
      ),
    );

    final data = json.decode(response.body) as Map<String, dynamic>;
    final token = data['token'] as String? ?? '';
    final userJson = data['user'] as Map<String, dynamic>? ?? {};

    final user = UserModel.fromJson(userJson);
    SessionService().setSession(user: user, token: token);
    return user;
  }

  Future<UserModel> me() async {
    final response = await _sendRequest(
      () =>
          _client.get(Uri.parse('$baseUrl/auth/me'), headers: _buildHeaders()),
    );

    final data = json.decode(response.body) as Map<String, dynamic>;
    final user = UserModel.fromJson(data['user'] as Map<String, dynamic>);
    SessionService().updateCurrentUser(user);
    return user;
  }

  Future<void> logout() async {
    try {
      await _sendRequest(
        () => _client.post(
          Uri.parse('$baseUrl/auth/logout'),
          headers: _buildHeaders(),
        ),
      );
    } finally {
      SessionService().clearSession();
    }
  }

  // ==========================================
  // DASHBOARD
  // ==========================================

  Future<DashboardData> getDashboard({
    String period = 'today',
    String? startDate,
    String? endDate,
  }) async {
    final queryParams = <String, String>{'period': period};
    if (startDate != null) queryParams['start_date'] = startDate;
    if (endDate != null) queryParams['end_date'] = endDate;

    final uri = Uri.parse(
      '$baseUrl/dashboard',
    ).replace(queryParameters: queryParams);
    final response = await _sendRequest(
      () => _client.get(uri, headers: _buildHeaders()),
    );
    final data = json.decode(response.body)['data'] as Map<String, dynamic>;
    return DashboardData.fromJson(data);
  }

  // ==========================================
  // PRODUCTS & STOCK
  // ==========================================

  Future<List<Product>> getProducts() async {
    final response = await _sendRequest(
      () =>
          _client.get(Uri.parse('$baseUrl/products'), headers: _buildHeaders()),
    );

    final data = json.decode(response.body)['data'] as List<dynamic>;
    return data
        .map((json) => Product.fromJson(json as Map<String, dynamic>))
        .toList();
  }

  // ==========================================
  // INWARD (KIRIM)
  // ==========================================

  Future<Map<String, dynamic>> createInward({
    int? variantId,
    String? productName,
    double? litres,
    int? volumeMl,
    required double quantity,
    String packageName = 'dona',
    int? costPrice,
    String? supplierName,
    String? invoiceNumber,
    String? operationId,
  }) async {
    final body = <String, dynamic>{
      'quantity': quantity,
      'package_name': packageName,
      'operation_id': ?operationId,
    };

    if (variantId != null) body['variant_id'] = variantId;
    if (productName != null) body['product_name'] = productName;
    if (litres != null) body['litres'] = litres;
    if (volumeMl != null) body['volume_ml'] = volumeMl;
    if (costPrice != null) body['cost_price'] = costPrice;
    if (supplierName != null && supplierName.isNotEmpty) {
      body['supplier_name'] = supplierName;
    }
    if (invoiceNumber != null && invoiceNumber.isNotEmpty) {
      body['invoice_number'] = invoiceNumber;
    }

    final response = await _sendRequest(
      () => _client.post(
        Uri.parse('$baseUrl/inward'),
        headers: _buildHeaders(),
        body: json.encode(body),
      ),
    );

    return json.decode(response.body) as Map<String, dynamic>;
  }

  // ==========================================
  // SALES (SOTUV)
  // ==========================================

  Future<SaleRecord> createSale({
    int? customerId,
    String? customerName,
    required List<Map<String, dynamic>> items,
    String paymentType = 'CASH',
    String paymentMethod = 'CASH',
    int? paidAmount,
    int? cashAccountId,
    String? operationId,
    String? notes,
  }) async {
    final body = <String, dynamic>{
      'items': items,
      'payment_type': paymentType,
      'payment_method': paymentMethod,
    };

    if (customerId != null) body['customer_id'] = customerId;
    if (customerName != null && customerName.isNotEmpty) {
      body['customer_name'] = customerName;
    }
    if (paidAmount != null) body['paid_amount'] = paidAmount;
    if (cashAccountId != null) body['cash_account_id'] = cashAccountId;
    if (operationId != null && operationId.isNotEmpty) {
      body['operation_id'] = operationId;
    }
    if (notes != null && notes.isNotEmpty) body['notes'] = notes;

    final response = await _sendRequest(
      () => _client.post(
        Uri.parse('$baseUrl/sales'),
        headers: _buildHeaders(),
        body: json.encode(body),
      ),
    );

    final data = json.decode(response.body)['data'] as Map<String, dynamic>;
    return SaleRecord.fromJson(data);
  }

  // ==========================================
  // CUSTOMERS
  // ==========================================

  Future<List<CustomerModel>> getCustomers({String? search}) async {
    final queryParams = <String, String>{};
    if (search != null && search.isNotEmpty) queryParams['search'] = search;

    final uri = Uri.parse(
      '$baseUrl/customers',
    ).replace(queryParameters: queryParams);
    final response = await _sendRequest(
      () => _client.get(uri, headers: _buildHeaders()),
    );
    final data = json.decode(response.body)['data'] as List<dynamic>;
    return data
        .map((c) => CustomerModel.fromJson(c as Map<String, dynamic>))
        .toList();
  }

  Future<CustomerModel> createCustomer({
    required String name,
    String? phone,
    String? storeName,
    String? address,
    int debtLimit = 0,
  }) async {
    final response = await _sendRequest(
      () => _client.post(
        Uri.parse('$baseUrl/customers'),
        headers: _buildHeaders(),
        body: json.encode({
          'name': name.trim(),
          'phone': phone?.trim(),
          'store_name': storeName?.trim(),
          'address': address?.trim(),
          'debt_limit': debtLimit,
        }),
      ),
    );

    final data = json.decode(response.body)['data'] as Map<String, dynamic>;
    return CustomerModel.fromJson(data);
  }

  // ==========================================
  // SUPPLIERS
  // ==========================================

  Future<List<SupplierModel>> getSuppliers({String? search}) async {
    final queryParams = <String, String>{};
    if (search != null && search.isNotEmpty) queryParams['search'] = search;

    final uri = Uri.parse(
      '$baseUrl/suppliers',
    ).replace(queryParameters: queryParams);
    final response = await _sendRequest(
      () => _client.get(uri, headers: _buildHeaders()),
    );
    final data = json.decode(response.body)['data'] as List<dynamic>;
    return data
        .map((s) => SupplierModel.fromJson(s as Map<String, dynamic>))
        .toList();
  }

  Future<SupplierModel> createSupplier({
    required String name,
    String? companyName,
    String? phone,
    String? address,
  }) async {
    final response = await _sendRequest(
      () => _client.post(
        Uri.parse('$baseUrl/suppliers'),
        headers: _buildHeaders(),
        body: json.encode({
          'name': name.trim(),
          'company_name': companyName?.trim(),
          'phone': phone?.trim(),
          'address': address?.trim(),
        }),
      ),
    );

    final data = json.decode(response.body)['data'] as Map<String, dynamic>;
    return SupplierModel.fromJson(data);
  }

  // ==========================================
  // CASH ACCOUNTS & PAYMENTS
  // ==========================================

  Future<List<CashAccountModel>> getCashAccounts() async {
    final response = await _sendRequest(
      () => _client.get(
        Uri.parse('$baseUrl/cash-accounts'),
        headers: _buildHeaders(),
      ),
    );

    final data = json.decode(response.body)['data'] as List<dynamic>;
    return data
        .map((a) => CashAccountModel.fromJson(a as Map<String, dynamic>))
        .toList();
  }

  Future<Map<String, dynamic>> createPayment({
    required String type, // 'customer' or 'supplier'
    required int partyId,
    required int amount,
    int? cashAccountId,
    String paymentMethod = 'CASH',
    String? operationId,
    String? notes,
  }) async {
    final body = <String, dynamic>{
      'type': type,
      'party_id': partyId,
      'amount': amount,
      'payment_method': paymentMethod,
    };
    if (cashAccountId != null) body['cash_account_id'] = cashAccountId;
    if (operationId != null && operationId.isNotEmpty) {
      body['operation_id'] = operationId;
    }
    if (notes != null && notes.isNotEmpty) body['notes'] = notes;

    final response = await _sendRequest(
      () => _client.post(
        Uri.parse('$baseUrl/payments'),
        headers: _buildHeaders(),
        body: json.encode(body),
      ),
    );

    return json.decode(response.body) as Map<String, dynamic>;
  }

  // ==========================================
  // SALES HISTORY
  // ==========================================

  Future<List<SaleRecord>> getSalesHistory() async {
    final response = await _sendRequest(
      () => _client.get(
        Uri.parse('$baseUrl/sales/history'),
        headers: _buildHeaders(),
      ),
    );

    final data = json.decode(response.body)['data'] as List<dynamic>;
    return data
        .map((s) => SaleRecord.fromJson(s as Map<String, dynamic>))
        .toList();
  }

  // ==========================================
  // REPORTS
  // ==========================================

  Future<ReportsData> getReports({
    String period = 'today',
    String? startDate,
    String? endDate,
  }) async {
    final queryParams = <String, String>{'period': period};
    if (startDate != null) queryParams['start_date'] = startDate;
    if (endDate != null) queryParams['end_date'] = endDate;

    final uri = Uri.parse(
      '$baseUrl/reports',
    ).replace(queryParameters: queryParams);
    final response = await _sendRequest(
      () => _client.get(uri, headers: _buildHeaders()),
    );
    final data = json.decode(response.body)['data'] as Map<String, dynamic>;
    return ReportsData.fromJson(data);
  }

  // ==========================================
  // CALCULATOR
  // ==========================================

  Future<Map<String, dynamic>> calculateCalculator({
    List<int>? variantIds,
  }) async {
    final response = await _sendRequest(
      () => _client.post(
        Uri.parse('$baseUrl/calculator'),
        headers: _buildHeaders(),
        body: json.encode({'variant_ids': variantIds ?? []}),
      ),
    );

    final data = json.decode(response.body)['data'] as Map<String, dynamic>;
    return data;
  }
}
