import 'dart:math';

/// Barqaror operation_id uchun RFC 4122 UUID v4 generator (Random.secure).
///
/// Bir qoralama (draft) uchun bir marta yaratiladi va qayta urinishlarda
/// (retry/timeout) aynan shu qiymat yuboriladi, shunda server bitta hujjat yaratadi.
class OperationId {
  static final Random _rng = Random.secure();

  static String generate() {
    final bytes = List<int>.generate(16, (_) => _rng.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
    bytes[8] = (bytes[8] & 0x3f) | 0x80; // RFC 4122 variant
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  static final RegExp _pattern = RegExp(
    r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
  );

  static bool isValid(String value) => _pattern.hasMatch(value);
}
