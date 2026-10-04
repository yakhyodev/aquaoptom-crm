import 'dart:convert';
import 'package:crypto/crypto.dart';

class PayloadFingerprint {
  static const List<String> ignoredKeys = [
    '_token',
    'request_id',
    'client_time',
    'nonce',
  ];

  /// Kanonik payload fingerprintini hisoblash (SHA-256).
  /// Backend PayloadFingerprint::compute($payload) va PWA bilan 100% bir xil natija beradi.
  static String compute(Map<String, dynamic> payload) {
    final canonical = canonicalize(payload);
    final jsonStr = json.encode(canonical);
    final bytes = utf8.encode(jsonStr);
    return sha256.convert(bytes).toString();
  }

  /// Rekursiv saralash va tozalash
  static dynamic canonicalize(dynamic data) {
    if (data is Map) {
      final sortedMap = <String, dynamic>{};
      final keys = data.keys.map((k) => k.toString()).toList()..sort();

      for (final key in keys) {
        if (ignoredKeys.contains(key)) {
          continue;
        }
        sortedMap[key] = canonicalize(data[key]);
      }
      return sortedMap;
    } else if (data is List) {
      return data.map((item) => canonicalize(item)).toList();
    } else if (data is String) {
      return data.trim();
    } else if (data is double) {
      // Float noaniqliklarini 4 ta xonagacha standartlashtirish
      return double.parse(data.toStringAsFixed(4));
    }
    return data;
  }
}
