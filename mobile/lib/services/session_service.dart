import 'package:flutter/foundation.dart';
import '../models/user_model.dart';

class SessionService extends ChangeNotifier {
  static final SessionService _instance = SessionService._internal();
  factory SessionService() => _instance;
  SessionService._internal();

  UserModel? _currentUser;
  String? _token;

  UserModel? get currentUser => _currentUser;
  String? get token => _token;
  bool get isAuthenticated => _token != null && _currentUser != null;

  void setSession({required UserModel user, required String token}) {
    _currentUser = user;
    _token = token;
    notifyListeners();
  }

  void updateCurrentUser(UserModel user) {
    _currentUser = user;
    notifyListeners();
  }

  void clearSession() {
    _currentUser = null;
    _token = null;
    notifyListeners();
  }
}
