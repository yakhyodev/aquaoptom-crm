import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'dart:convert';
import '../models/user_model.dart';

class SessionService extends ChangeNotifier {
  static final SessionService _instance = SessionService._internal();
  factory SessionService() => _instance;
  SessionService._internal();

  UserModel? _currentUser;
  String? _token;
  static const channel = MethodChannel('aquaoptom/session');

  Future<void> restoreSession() async {
    try {
      final raw = await channel.invokeMethod<String>('read');
      if (raw == null) return;
      final data = json.decode(raw) as Map<String, dynamic>;
      final user = UserModel.fromJson(data['user'] as Map<String, dynamic>);
      final token = data['token'] as String;
      if (user.id <= 0 || token.isEmpty) return;
      _currentUser = user;
      _token = token;
      notifyListeners();
    } catch (_) {
      try {
        await clearSession();
      } catch (_) {
        /* Invalid native session is never restored. */
      }
    }
  }

  Future<void> _persist() async {
    try {
      await channel.invokeMethod(
        'write',
        json.encode({'user': _currentUser!.toJson(), 'token': _token}),
      );
    } on MissingPluginException {
      // Desktop tests and unsupported platforms retain a memory-only session.
    }
  }

  UserModel? get currentUser => _currentUser;
  String? get token => _token;
  bool get isAuthenticated => _token != null && _currentUser != null;

  Future<void> setSession({
    required UserModel user,
    required String token,
  }) async {
    _currentUser = user;
    _token = token;
    notifyListeners();
    await _persist();
  }

  Future<void> updateCurrentUser(UserModel user) async {
    _currentUser = user;
    notifyListeners();
    if (_token != null) await _persist();
  }

  Future<void> clearSession() async {
    _currentUser = null;
    _token = null;
    notifyListeners();
    try {
      await channel.invokeMethod('clear');
    } on MissingPluginException {
      /* No native storage on this platform. */
    }
  }
}
