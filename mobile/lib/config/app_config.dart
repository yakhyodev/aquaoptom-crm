class AppConfig {
  /// Standart API URL --dart-define orqali uzatiladi.
  /// Android emulyator uchun default: http://10.0.2.2:8000/api
  static const String _defaultUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );

  static String _customBaseUrl = _defaultUrl;

  static String get apiBaseUrl => _customBaseUrl;

  static void setApiBaseUrl(String url) {
    if (url.trim().isNotEmpty) {
      _customBaseUrl = url.trim().endsWith('/api') ? url.trim() : '${url.trim()}/api';
    }
  }

  static void resetApiBaseUrl() {
    _customBaseUrl = _defaultUrl;
  }

  /// Web boshqaruv paneli havolasi (Admin amallari uchun)
  static String get webAdminUrl {
    final base = _customBaseUrl.replaceAll(RegExp(r'/api$'), '');
    return '$base/admin';
  }
}
