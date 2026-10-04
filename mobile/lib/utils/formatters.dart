import 'package:intl/intl.dart';

class Formatters {
  static final NumberFormat _currencyFormatter = NumberFormat('#,###', 'uz_UZ');
  static final DateFormat _dateTimeFormatter = DateFormat('dd.MM.yyyy HH:mm');
  static final DateFormat _dateFormatter = DateFormat('dd.MM.yyyy');

  /// Pul miqdorini aniq butun son (integer) sifatida formatlash (hech qanday float yo'q)
  static String formatMoney(int amount) {
    final formatted = _currencyFormatter.format(amount).replaceAll(',', ' ');
    return "$formatted so'm";
  }

  /// Hajmni formatlash (0.5 L, 1.5 L, 18.9 L)
  static String formatLitres(double litres) {
    if (litres == litres.roundToDouble()) {
      return '${litres.toInt()} L';
    }
    return '${litres.toStringAsFixed(1)} L';
  }

  /// Sana va vaqt
  static String formatDateTime(DateTime dateTime) {
    return _dateTimeFormatter.format(dateTime.toLocal());
  }

  /// Faqat sana
  static String formatDate(DateTime dateTime) {
    return _dateFormatter.format(dateTime.toLocal());
  }
}
